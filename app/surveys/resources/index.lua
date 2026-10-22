-- Placeholder for survey
-- survey/index.lua
--
--
-- (c) 2026 Joseph Nadiv <ynadiv@corpit.xyz>
require "resources.functions.config";
require "resources.functions.split";
debug.sql = true;
json = freeswitch.JSON();

-- connect to the database
local Database = require "resources.functions.database";
dbh = Database.new('system');

-- Get which survey we're doing
survey_uuid = argv[1];
domain_name = session:getVariable("domain_name");
domain_uuid = session:getVariable("domain_uuid");

-- set the defaults
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;

-- Function to save vote
function save_vote(vote, reason, sequence_id)
    if (survey_customer_uuid == nil) then
        --define uuid function
            local random = math.random;
            local function uuid()
                local template ='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
                return string.gsub(template, '[xy]', function (c)
                    local v = (c == 'x') and random(0, 0xf) or random(8, 0xb);
                    return string.format('%x', v);
                end)
            end
        survey_customer_uuid = uuid();
        local sql = "INSERT INTO v_survey_customer (survey_customer_uuid, caller_id_number, ";
        sql = sql .. " caller_id_name, domain_uuid, gender, age, zip_code)";
        sql = sql .. " values (:survey_customer_uuid, :caller_id_number, :caller_id_name, :domain_uuid, :gender, :age, :zip_code); ";
        local params = {
            caller_id_name = caller_id_name,
            caller_id_number = caller_id_number,
            domain_uuid = domain_uuid,
            survey_customer_uuid = survey_customer_uuid,
            gender = gender or 'NULL',
            age = age or 'NULL',
            zip_code = zip_code or 'NULL'
        }
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[survey_customer] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params);
    end
    -- save the vote
    local sql = "INSERT INTO v_survey_votes (survey_customer_uuid, vote, call_uuid, survey_uuid, sequence_id, domain_uuid";
    if reason ~= nil then
        sql = sql .. ", reason";
    end
    sql = sql .. ")";
    sql = sql .. " values (:survey_customer_uuid, :vote, :uuid, :survey_uuid, :sequence_id, :domain_uuid"
    if reason ~= nil then
        sql = sql .. ", :reason";
    end
    sql = sql .. ")";
    local params = {
        survey_customer_uuid = survey_customer_uuid,
        vote = vote,
        uuid = uuid,
        survey_uuid = survey_uuid,
        sequence_id = sequence_id,
        domain_uuid = domain_uuid,
        reason = reason
    }
    dbh:query(sql, params);
end


-- set the recordings directory
local recordings_dir = recordings_dir .. "/" .. domain_name .. "/";

-- get session variables
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
uuid = session:getVariable("uuid");
survey_questions = {};

-- Strip E.164 plus sign
if (string.sub(caller_id_number, 1, 1) == "+") then
    caller_id_number = string.sub(caller_id_number, 2);
end

session:answer();
session:execute("playback", "silence_stream://300");

-- Reject bad callerID
if (string.len(caller_id_number) < 10 or tonumber(caller_id_number) == nil) then
    -- TODO play rejection
    -- session:streamFile(audio_dir .. "bad_caller_id.wav");
    session:hangup();
end

-- Check if customer is in table
local sql = [[SELECT survey_customer_uuid FROM v_survey_customer
            WHERE domain_uuid = :domain_uuid
            AND caller_id_number = :caller_id_number]];
local params = {
    domain_uuid = domain_uuid,
    caller_id_number = caller_id_number
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice",
        "[survey_customer] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
end
dbh:query(sql, params, function(row)
    survey_customer_uuid = row["survey_customer_uuid"];
end);

-- Get survey config
if session:ready() then
    local sql = [[SELECT * FROM v_surveys
					WHERE domain_uuid = :domain_uuid
					AND survey_uuid = :survey_uuid]];
    local params = {
        domain_uuid = domain_uuid,
        survey_uuid = survey_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[survey] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        greeting_file = row["greeting"];
        exit_file = row["exit_file"];
        age_file = row['age_file'];
        zip_code_file = row['zip_code_file'];
        greeting_suffix = row['greeting_suffix'];
        gender_file = row['gender_file'];
        retake_file = row['retake_file'];
        question_answered_file = row['question_answered_file'];
        exit_action = row["exit_action"];
        reason_file = row["reason_file"];
        reason_0_file = row["reason_0_file"];
        ask_reason_below = row["ask_reason_below"];
        ask_only_odd_even = row["ask_only_odd_even"];
    end);

    if ask_only_odd_even ~= nil and ask_only_odd_even == "true" then
        local seconds = os.time() 
        even_second = seconds % 2 == 0
    end

end

if session:ready() and survey_customer_uuid ~= nil then
    -- Did you vote already this week?
    local sql = [[SELECT count(vote) FROM v_survey_votes
            WHERE domain_uuid = :domain_uuid
            AND survey_uuid = :survey_uuid 
            AND survey_customer_uuid = :survey_customer_uuid]];
    local params = {
        domain_uuid = domain_uuid,
        survey_customer_uuid = survey_customer_uuid,
        survey_uuid = survey_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice",
            "[survey_customer] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        voted_already = row["count"];
    end);
    if tonumber(voted_already) ~= nil and tonumber(voted_already) > 0 then
        -- it depends, if there's retake_file then allow retake
        if retake_file ~= nil and string.len(retake_file) > 0 then
            local try_again = session:playAndGetDigits(1, 1, 3, 5000, "#", recordings_dir .. retake_file, "", "[12]");
            if try_again == "1" then
                local sql = [[DELETE FROM v_survey_votes
                WHERE domain_uuid = :domain_uuid
                AND survey_uuid = :survey_uuid 
                AND survey_customer_uuid = :survey_customer_uuid]];
                local params = {
                    domain_uuid = domain_uuid,
                    survey_customer_uuid = survey_customer_uuid,
                    survey_uuid = survey_uuid
                };
                dbh:query(sql, params);
            else
                session:hangup();
            end
        else
            -- You voted already
            local f = io.open(recordings_dir .. "already-voted.wav", "r");
            if f ~= nil then 
                io.close(f)
                session:streamFile(recordings_dir .. "already-voted.wav");
            end
            session:hangup();
        end
    end
end

if session:ready() then
    -- Play greeting
    session:streamFile(recordings_dir .. greeting_file);
end

        if session:ready() and greeting_suffix ~= nil and string.len(greeting_suffix) > 0 then
            session:streamFile(recordings_dir .. greeting_suffix)
        end

-- Demographic data
        if session:ready() and age_file ~= nil and string.len(age_file) > 0 then
            session:flushDigits();
            local exit = false;
            local tries = 0;
            while (session:ready() and exit == false) do
                age = session:playAndGetDigits(1, 2, 3, digit_timeout, "#", recordings_dir .. age_file, "", "\\d+");
                if tonumber(age) ~= nil and tonumber(age) > 3 then
                    exit = true;
                else
                    tries = tries + 1;
                    if tries == max_tries then session:hangup(); end;
                end
            end
        end

        if session:ready() and gender_file ~= nil and string.len(gender_file) > 0 then
            session:flushDigits();
            local exit = false;
            local tries = 0;
            while (session:ready() and exit == false) do
                local gender_num = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. gender_file,
                    "", "[12]");
                if tonumber(gender_num) ~= nil and tonumber(gender_num) > 0 and tonumber(gender_num) < 3 then
                    exit = true;
                    if tonumber(gender_num) == 1 then
                        gender = "male";
                    else
                        gender = "female";
                    end
                else
                    tries = tries + 1;
                    if tries == max_tries then session:hangup(); end;
                end
            end
        end

        if session:ready() and zip_code_file ~= nil and string.len(zip_code_file) > 0 then
            session:flushDigits();
            local exit = false;
            local tries = 0;
            while (session:ready() and exit == false) do
                zip_code = session:playAndGetDigits(5, 5, 3, digit_timeout, "#", recordings_dir .. zip_code_file,
                    "", "\\d+");
                if tonumber(zip_code) ~= nil and tonumber(zip_code) > 0 then
                    exit = true;
                else
                    tries = tries + 1;
                    if tries == max_tries then session:hangup(); end;
                end
            end
        end

-- loop through questions
if session:ready() then
    local sql = [[SELECT * FROM v_survey_questions
        WHERE domain_uuid = :domain_uuid
        AND survey_uuid = :survey_uuid
	ORDER BY sequence_id]];
    local params = {
        domain_uuid = domain_uuid,
        survey_uuid = survey_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice",
            "[survey_questions] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        local question = {};
        question['recording'] = row['recording'];
        question['highest_number'] = row['highest_number'];
        table.insert(survey_questions, row['sequence_id'], question);
    end);
end

if session:ready() then
    for i, question in ipairs(survey_questions) do    
        if ask_only_odd_even == "true" and even_second and i % 2 ~= 0 then
            -- Question is odd but we ask only even
        elseif ask_only_odd_even == "true" and not even_second and i % 2 == 0 then
            -- Question is even but we ask only odd
        else
            session:flushDigits();
            local exit = false;
            local reason = nil;
            local tries = 0;
            while (session:ready() and exit == false) do
                if question['recording_suffix'] ~= nil and string.len(question['recording_suffix']) > 0 then
                    session:streamFile(recordings_dir .. question['recording']);
                    dtmf_digits = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. question["recording_suffix"],
                    "", "");
                else
                    dtmf_digits = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. question["recording"],
                    "", "");
                end
                if tonumber(dtmf_digits) == nil or tonumber(dtmf_digits) <= tonumber(question['highest_number']) then
                    exit = true;
                else
                    tries = tries + 1;
                    if tries == max_tries then session:hangup(); end;
                end
            end

            if tonumber(dtmf_digits) ~= nil then
                -- If they voted 0 then ask for a reason
                if tonumber(dtmf_digits) == 0 and reason_0_file ~= nil and string.len(reason_0_file) > 0 then
                    session:flushDigits();
                    local exit = false;
                    local tries = 0;
                    while (session:ready() and exit == false) do
                        reason = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. reason_0_file,
                            "", "");
                        if tonumber(reason) ~= nil and tonumber(reason) > 0 then
                            exit = true;
                        else
                            tries = tries + 1;
                            if tries == max_tries then session:hangup(); end;
                        end
                    end
                elseif tonumber(ask_reason_below) ~= nil and tonumber(dtmf_digits) <= tonumber(ask_reason_below) and reason_file ~= nil and string.len(reason_file) ~= 0 then
                    session:flushDigits();
                    local exit = false;
                    local tries = 0;
                    while (session:ready() and exit == false) do
                        reason = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. reason_file,
                            "", "");
                        if tonumber(reason) ~= nil and tonumber(reason) > 0 then
                            exit = true;
                        else
                            tries = tries + 1;
                            if tries == max_tries then session:hangup(); end;
                        end
                    end
                end
                if question_answered_file ~= nil and string.len(question_answered_file) > 0 then
                    session:streamFile(recordings_dir .. question_answered_file);
                end
                save_vote(dtmf_digits, reason, i)
            end
        end
    end
end

-- Play exit file
if session:ready() and exit_file ~= nil and string.len(exit_file) > 0 then
    session:streamFile(recordings_dir .. exit_file);
end

-- Transfer to exit_action
if exit_action ~= nil and string.len(exit_action) > 0 then
    local exit_action_app, exit_action_params = split_first(exit_action, ":", true);
    session:execute(exit_action_app, exit_action_params);
end
