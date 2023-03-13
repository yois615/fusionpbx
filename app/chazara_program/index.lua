-- Get vars from environment
--
-- This file belongs to a standalone project
-- by Chickens for Shabbos for Chazara
--
-- (c) 2023 by Joseph Nadiv <ynadiv@corpit.xyz>

require "resources.functions.config";
require "resources.functions.split";
debug.sql = true;
json = freeswitch.JSON();

-- connect to the database
local Database = require "resources.functions.database";
dbh = Database.new('system');

domain_name = session:getVariable("domain_name");
domain_uuid = session:getVariable("domain_uuid");

-- set the defaults
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;

-- set the recordings directory
local recordings_dir = recordings_dir .. "/" .. domain_name .. "/";

-- get session variables
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
uuid = session:getVariable("uuid");

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

-- Get survey config
if session:ready() then
    local sql = [[SELECT * FROM v_chazara_ivrs
					WHERE domain_uuid = :domain_uuid]];
    local params = {
        domain_uuid = domain_uuid
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        greeting_recording = row["greeting_recording"];
        grade_recording = row["grade_recording"];
        chazara_ivr_uuid = row["chazara_ivr_uuid"];
    end);
end

-- Play greeting pagd
::start_menu::
if session:ready() then
    session:flushDigits();
    local exit = false;
    while (session:ready() and exit == false) do
        caller_type = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. greeting_recording, "", "[128]");
        if tonumber(caller_type) ~= nil then
            exit = true;
        end
    end
end

-- Transfer 8 to *732
if caller_type == "8" then
    session:execute("transfer", "*732 XML " .. domain_name);
end

-- Play grade menu, first find max grade
    local sql = [[SELECT MAX(grade) as max_grade FROM v_chazara_teachers
            WHERE domain_uuid = :domain_uuid]];
    local params = {
        domain_uuid = domain_uuid,
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        max_grade = row["max_grade"];
    end);
    if max_grade > 9 then
        grade_max_digits = 2;
    else
        grade_max_digits = 1;
    end

::grade_menu::
session:flushDigits();
local exit = false;
parallel_recording = nil;
while (session:ready() and exit == false) do
    grade = session:playAndGetDigits(1, grade_max_digits, 3, digit_timeout, "#", recordings_dir .. grade_recording, "", "");
    if grade == "*" then goto start_menu; end;
    if tonumber(grade) ~= nil then
        -- Inspect database if that grade exists, and how many parallels
        local sql = [[SELECT count(chazara_teachers_uuid) as count FROM v_chazara_teachers
                WHERE domain_uuid = :domain_uuid
                AND grade = :grade]];
        local params = {
            domain_uuid = domain_uuid,
            grade = grade
        };
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params, function(row)
            count = row["count"];
        end);
        if count > 0 then
            exit = true;
        end
        if count > 1 then
            local sql = [[SELECT recording FROM v_chazara_ivr_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_ivr_uuid = :chazara_ivr_uuid
                    AND grade = :grade]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_ivr_uuid = chazara_ivr_uuid,
                grade = grade
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                parallel_recording = row["recording"];
            end);
        end
    end
end


-- play parallel menu if exists
if parallel_recording ~= nil and string.len(parallel_recording) > 0 then
    session:flushDigits();
    local exit = false;
    while (session:ready() and exit == false) do
        parallel = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. parallel_recording, "", "");
        if parallel == "*" then goto grade_menu; end;
        if tonumber(parallel) ~= nil then
            local sql = [[SELECT chazara_teachers_uuid FROM v_chazara_teachers
                    WHERE domain_uuid = :domain_uuid
                    AND grade = :grade
                    AND parallel = :parallel]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_ivr_uuid = chazara_ivr_uuid,
                grade = grade,
                parallel = parallel
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                chazara_teachers_uuid = row["chazara_teachers_uuid"];
            end);
            if chazara_teachers_uuid ~= nil and string.len(chazara_teachers_uuid) > 0 then
                exit = true;
            else
                --TODO playback invalid 
            end
        end
    end
end



if caller_type == "2" then
    -- if teacher mode ask for pin    
end

-- Ask for recording ID
-- CHeck DB if exists
-- If student play recording, if teacher play options.