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
    -- session:streamFile(recordings_dir .. "bad_caller_id.wav");
    --session:hangup();
end

-- Playback callback function
    function cpb_dtmf_input(session, type, data, arg)
        if (type == "dtmf") then
            freeswitch.consoleLog("INFO", "control_playback got digit " .. data['digit'] .. "\n");
            if (data['digit'] == "*") then
                exit = true;
                return 0;
            elseif (data['digit'] == "1") then
                return ("seek:-15000");
            elseif (data['digit'] == "3") then
                return ("seek:+15000");
            elseif (data['digit'] == "4") then
                return ("seek:-60000");
            elseif (data['digit'] == "6") then
                return ("seek:+60000");
            elseif (data['digit'] == "5") then
                return ("pause");
            elseif (data['digit'] == "2") then
                return ("volume:+1");
            elseif (data['digit'] == "8") then
                return ("volume:-1");
            elseif (data['digit'] == "7") then
                -- https://github.com/signalwire/freeswitch/pull/244
                return ("speed:-1");
            elseif (data['digit'] == "9") then
                return ("speed:+1");
            elseif (data['digit'] == "0") then
                return("restart"); --start over
            else
                return 0;
            end
        end
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
    local timeout = 0;
    while (session:ready() and exit == false) do
        caller_type = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. greeting_recording, "", "[1280]");
        if tonumber(caller_type) ~= nil then
            if caller_type == "0" then
                session:streamFile(recordings_dir .. "system_instructions.wav");
            else
                exit = true;
            end
        end
        timeout = timeout + 1
        if timeout > 3 then
            session:hangup();
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
    if tonumber(max_grade) > 9 then
        grade_max_digits = 2;
    else
        grade_max_digits = 1;
    end

::grade_menu::
session:flushDigits();
local exit = false;
local timeout = 0;
parallel_recording = nil;
while (session:ready() and exit == false) do
    grade = session:playAndGetDigits(1, grade_max_digits, 3, digit_timeout, "#", recordings_dir .. grade_recording, "", "");
    if grade == "*" then goto start_menu; end;
    if tonumber(grade) ~= nil then
        -- Inspect database if that grade exists, and how many parallels
        local sql = [[SELECT count(chazara_teacher_uuid) as count FROM v_chazara_teachers
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
            count = tonumber(row["count"]);
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
    timeout = timeout + 1
    if timeout > 3 then
        session:hangup();
    end
end


-- play parallel menu if exists
if parallel_recording ~= nil and string.len(parallel_recording) > 0 then
    session:flushDigits();
    local exit = false;
    local timeout = 0;
    while (session:ready() and exit == false) do
        parallel = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. parallel_recording, "", "");
        if parallel == "*" then goto grade_menu; end;
        if tonumber(parallel) ~= nil then
            local sql = [[SELECT chazara_teacher_uuid, pin FROM v_chazara_teachers
                    WHERE domain_uuid = :domain_uuid
                    AND grade = :grade
                    AND parallel_class_id = :parallel]];
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
                chazara_teacher_uuid = row["chazara_teacher_uuid"];
                pin = row["pin"];
            end);
            if chazara_teacher_uuid ~= nil and string.len(chazara_teacher_uuid) > 0 then
                exit = true;
            else
                session:streamFile(recordings_dir .. "invalid.wav");
            end
        end
        timeout = timeout + 1;
        if timeout > 3 then
            session:hangup();
        end
    end
end

if caller_type == "2" then
    local tries = 0;
    while (session:ready() and tries < 3) do
        session:flushDigits();
        local dtmf_digits = session:playAndGetDigits(1, string.len(pin), 3, digit_timeout, "#", recordings_dir .. "enter_pin.wav", recordings_dir .. "invalid.wav", "\\d+");
        if dtmf_digits == pin then
            teacher_auth = true; 
            break;
        else
            session:streamFile(recordings_dir .. "invalid.wav");
            tries = tries + 1;
        end
    end
    if teacher_auth == false then session:hangup(); end;
end

if teacher_auth ~= true then
    -- This is the entire student flow
    while session:ready() do
        recording_id = session:playAndGetDigits(3, 3, 3, digit_timeout, "#", recordings_dir .. "student_select_class.wav", recordings_dir .. "invalid.wav", "");
        if tonumber(recording_id) == nil then
            goto grade_menu
            break
        else
        -- Find recording
            local sql = [[SELECT recording_filename, chazara_recording_uuid FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND recording_id = :recording_id]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                recording_id = recording_id,
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                recording_filename = row["recording_filename"];
                chazara_recording_uuid = row["chazara_recording_uuid"];
            end);

            if recording_filename ~= nil and string.len(recording_filename) > 0 then
                local start_epoch = os.time();
                -- Play file
                session:setInputCallback("cpb_dtmf_input", "");
                session:streamFile(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_filename);
                session:unsetInputCallback();
                -- Insert record into CDR
                local sql = "INSERT INTO v_chazara_cdrs (chazara_recording_uuid, call_uuid, start_epoch, "; 
                sql = sql .. "duration, caller_id_number, caller_id_name) "
                sql = sql .. "values (:chazara_recording_uuid, :uuid, :start_epoch, :duration, :caller_id_number, :caller_id_name)";
                local params = {
                    chazara_recording_uuid = chazara_recording_uuid,
                    uuid = uuid,
                    start_epoch = start_epoch,
                    caller_id_number = caller_id_number,
                    caller_id_name = caller_id_name,
                    duration = os.time() - start_epoch
                }
                dbh:query(sql, params);
                recording_filename = nil;
                chazara_recording_uuid = nil;
            else
                -- Does not exist
                session:streamFile(recordings_dir .. "recording_not_available.wav");
            end
        end
    end
end

if teacher_auth == true then
   -- This is the teacher flow
    local function record_class()
        --define uuid function
            local random = math.random;
            local function gen_uuid()
                local template ='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
                return string.gsub(template, '[xy]', function (c)
                    local v = (c == 'x') and random(0, 0xf) or random(8, 0xb);
                    return string.format('%x', v);
                end)
            end
            local recording_uuid = gen_uuid();
            session:streamFile("phrase:voicemail_record_message");
            session:setInputCallback("on_dtmf", "");
            session:execute("playback","silence_stream://200");
            session:streamFile("tone_stream://L=1;%(1000, 0, 640)");
            os.remove(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_uuid .. ".wav")
            session:recordFile(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_uuid ..".wav", 3600, 500, 10);
            session:unsetInputCallback();
            return recording_uuid;
        end

        local function verify_recording(recording_uuid, recording_filename)
            local incomplete = true;
            local timeout = 0;
            while (incomplete and timeout < 3 and session:ready()) do

                dtmf_digits = "";
                session:flushDigits();
                -- To playback your recording, press 1, to save your recording, press 2.  To append to the end of your recording, press 3. To delete and return to menu, press 4.
                dtmf_digits = session:playAndGetDigits(0, 1, 3, 3000, "#", recordings_dir .. "verify_recording.wav", "", "\\d+");

                if (not session:ready()) or dtmf_digits == "2" then
                    incomplete = false;

                    -- Remove old record
                    local sql = [[DELETE from v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND recording_id = :recording_id]];
                    local params = {
                        domain_uuid = domain_uuid,
                        chazara_teacher_uuid = chazara_teacher_uuid,
                        recording_id = recording_id
                    };
                    if (debug["sql"]) then
                        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
                    end
                    dbh:query(sql, params)

                    -- Save new record
                    local sql = [[INSERT INTO v_chazara_recordings (chazara_recording_uuid, domain_uuid, recording_id, 
                                recording_name, recording_filename, chazara_teacher_uuid, created_epoch, length) 
                                VALUES (:chazara_recording_uuid, :domain_uuid, :recording_id, :recording_id, :recording_filename, 
                                :chazara_teacher_uuid, :created_epoch, :length)]]

                    local params = {
                        chazara_recording_uuid = recording_uuid;
                        domain_uuid = domain_uuid,
                        recording_id = recording_id,
                        recording_name = recording_id,
                        recording_filename = recording_filename;
                        chazara_teacher_uuid = chazara_teacher_uuid,
                        created_epoch = os.time(),
                        length = os.execute('soxi -D ' .. recordings_dir .. "/" .. chazara_teacher_uuid .. "/" .. recording_filename);
                    }

                    if (debug["sql"]) then
                        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
                    end
                    dbh:query(sql, params)
                    return;
                elseif (dtmf_digits == "1") then
                    session:setInputCallback("cpb_dtmf_input", "");
                    session:streamFile(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_filename);
                    session:unsetInputCallback();
                elseif (dtmf_digits == "3") then
                    -- apend requires <action application="set" data="RECORD_APPEND=true"/>
                    session:setVariable("RECORD_APPEND", "true");
                    session:setInputCallback("on_dtmf", "");
                    dtmf_digits = session:playAndGetDigits(0, 1, 1, 500, "#", "phrase:voicemail_record_message", "", "\\d+")
                    dtmf_digits = '';
                    session:execute("playback", "silence_stream://200");
                    session:streamFile("tone_stream://L=1;%(500, 0, 640)");
                    result = session:recordFile(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_filename, 3600, 500, 10);
                    session:unsetInputCallback();
                    session:setVariable("RECORD_APPEND", "false");
                    timeout = 0;
                elseif (dtmf_digits == "4") then
                    incomplete = false;
                    -- Remove record
                    local sql = [[DELETE from v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND recording_id = :recording_id]];
                    local params = {
                        domain_uuid = domain_uuid,
                        chazara_teacher_uuid = chazara_teacher_uuid,
                        recording_id = recording_id
                    };
                    if (debug["sql"]) then
                        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
                    end
                    dbh:query(sql, params)
                    os.remove(recordings_dir .. chazara_teacher_uuid .. "/" .. recording_filename);
                end
            timeout = timeout + 1;
        end
    end

   while session:ready() do
        recording_id = session:playAndGetDigits(3, 3, 3, digit_timeout, "#", recordings_dir .. "teacher_select_class.wav", recordings_dir .. "invalid.wav", "");
        if tonumber(recording_id) == nil then
            goto grade_menu
            break
        elseif recording_id == "000" then
            -- Change password
            local new_password = session:playAndGetDigits(4, 7, 3, 3000, "#", recordings_dir .. "choose_password.wav", recordings_dir .. "invalid.wav", "\\d+");
            if tonumber(new_password) ~= nil then
                session:say(new_password, "en", "number", "iterated");
                local sql = [[UPDATE v_chazara_teachers set pin = :pin 
                            WHERE chazara_teacher_uuid = :chazara_teacher_uuid AND domain_uuid = :domain_uuid]]
                local params = {
                    pin = new_password,
                    chazara_teacher_uuid = chazara_teacher_uuid,
                    domain_uuid = domain_uuid
                }
                dbh:query(sql, params);
            else
                session:streamFile(recordings_dir .. "invalid.wav");
            end
        else
        -- Find recording
            local sql = [[SELECT recording_filename, chazara_recording_uuid FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND recording_id = :recording_id]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                recording_id = recording_id,
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                recording_filename = row["recording_filename"];
                chazara_recording_uuid = row["chazara_recording_uuid"];
            end);

            if recording_filename ~= nil and string.len(recording_filename) > 0 then
                -- if exists ask if listen, append, delete
                verify_recording(chazara_recording_uuid, recording_filename);
            else
                -- Does not exist, begin record
                chazara_recording_uuid = record_class();
                verify_recording(chazara_recording_uuid, chazara_recording_uuid .. ".wav");
                
            end
            recording_filename = nil;
            chazara_recording_uuid = nil;
        end
    end
end