-- Placeholder for survey
-- circle_survey/index.lua
--
-- This file belongs to a standalone project
-- by the Circle to survey callers about magazine
-- articles and vote.
--
-- (c) 2023 The Voice of Lakewood, Circle Magazine
-- and Joseph Nadiv <ynadiv@corpit.xyz>
require "resources.functions.config";
require "resources.functions.split";
debug.sql = true;
json = freeswitch.JSON();

-- connect to the database
local Database = require "resources.functions.database";
dbh = Database.new('system');

-- Get which survey we're doing
circle_survey_uuid = argv[1];
domain_name = session:getVariable("domain_name");
domain_uuid = session:getVariable("domain_uuid");

-- set the defaults
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;
-- TODO
voicemail_id = "350";

-- Function to save vote
function save_vote(vote, sequence_id)
    if (circle_survey_customer_uuid == nil) then
        --define uuid function
            local random = math.random;
            local function uuid()
                local template ='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
                return string.gsub(template, '[xy]', function (c)
                    local v = (c == 'x') and random(0, 0xf) or random(8, 0xb);
                    return string.format('%x', v);
                end)
            end
        circle_survey_customer_uuid = uuid();
        local sql = "INSERT INTO v_circle_survey_customer (circle_survey_customer_uuid, caller_id_number, caller_id_name, domain_uuid)";
        sql = sql .. " values (:circle_survey_customer_uuid, :caller_id_number, :caller_id_name, :domain_uuid); ";
        local params = {
            caller_id_name = caller_id_name,
            caller_id_number = caller_id_number,
            domain_uuid = domain_uuid,
            circle_survey_customer_uuid = circle_survey_customer_uuid
        }
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[circle_survey_customer] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params);
    end
    -- save the vote
    local sql = "INSERT INTO v_circle_survey_votes (circle_survey_customer_uuid, vote, call_uuid, circle_survey_uuid, sequence_id, domain_uuid)"
    sql = sql .. " values (:circle_survey_customer_uuid, :vote, :uuid, :circle_survey_uuid, :sequence_id, :domain_uuid)";
    local params = {
        circle_survey_customer_uuid = circle_survey_customer_uuid,
        vote = vote,
        uuid = uuid,
        circle_survey_uuid = circle_survey_uuid,
        sequence_id = sequence_id,
        domain_uuid = domain_uuid
    }
    dbh:query(sql, params);
end


-- set the recordings directory
local recordings_dir = recordings_dir .. "/" .. domain_name .. "/";

-- get session variables
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
uuid = session:getVariable("uuid");
voicemail_message_uuid = uuid;
voicemail_dir = "/var/lib/freeswitch/storage/voicemail/default/the-circle.corpit.xyz";
survey_questions = {};

-- Strip E.164 plus sign
if (string.sub(caller_id_number, 1, 1) == "+") then
    caller_id_number = string.sub(caller_id_number, 2);
end

session:answer();

-- Reject bad callerID
if (string.len(caller_id_number) < 10 or tonumber(caller_id_number) == nil) then
    -- TODO play rejection
    -- session:streamFile(audio_dir .. "bad_caller_id.wav");
    session:hangup();
end

-- Check if customer is in table
local sql = [[SELECT circle_survey_customer_uuid FROM v_circle_survey_customer
            WHERE domain_uuid = :domain_uuid
            AND caller_id_number = :caller_id_number]];
local params = {
    domain_uuid = domain_uuid,
    caller_id_number = caller_id_number
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice",
        "[circle_survey_customer] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
end
dbh:query(sql, params, function(row)
    circle_survey_customer_uuid = row["circle_survey_customer_uuid"];
end);

if circle_survey_customer_uuid ~= nil then
    -- TODO you voted already
    session:hangup();
end

-- Get survey config
if session:ready() then
    local sql = [[SELECT * FROM v_circle_surveys
					WHERE domain_uuid = :domain_uuid
					AND circle_survey_uuid = :circle_survey_uuid]];
    local params = {
        domain_uuid = domain_uuid,
        circle_survey_uuid = circle_survey_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[circle_survey] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        greeting_file = row["greeting"];
        exit_file = row["exit_file"];
        exit_action = row["exit_action"];
    end);

    -- Play greeting
    session:streamFile(recordings_dir .. greeting_file);
end

-- loop through questions
if session:ready() then
    local sql = [[SELECT * FROM v_circle_survey_questions
        WHERE domain_uuid = :domain_uuid
        AND circle_survey_uuid = :circle_survey_uuid]];
    local params = {
        domain_uuid = domain_uuid,
        circle_survey_uuid = circle_survey_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice",
            "[circle_survey_questions] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
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
        session:flushDigits();
        local exit = false;
        while (session:ready() and exit == false) do
            dtmf_digits = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. question["recording"],
                "", "");
            if tonumber(dtmf_digits) == nil or tonumber(dtmf_digits) <= tonumber(question['highest_number']) then
                exit = true;
            end
        end

        if tonumber(dtmf_digits) ~= nil then
            save_vote(dtmf_digits, i)
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