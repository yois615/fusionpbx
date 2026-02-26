-- Get vars from environment
--
-- This file belongs to a standalone project
-- by Chickens for Shabbos for Chazara
--
-- (c) 2023 by Joseph Nadiv <ynadiv@corpit.xyz>

require "resources.functions.config";
require "resources.functions.split";
require "resources.functions.is_uuid";
debug.sql = true;
json = freeswitch.JSON();
api = freeswitch.API();

-- connect to the database
local Database = require "resources.functions.database";
dbh = Database.new('system');

domain_name = session:getVariable("domain_name");
domain_uuid = session:getVariable("domain_uuid");

-- set the defaults
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;

recording_filename = {}
chazara_recording_uuid = {}
chazara_daf_teacher_uuid = {}

-- set the recordings directory
local recordings_dir = recordings_dir .. "/" .. domain_name .. "/";

-- get session variables
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
uuid = session:getVariable("uuid");

--load lazy settings library
local Settings = require "resources.functions.lazy_settings";

--get the system settings
	local settings = Settings.new(dbh, domain_name, domain_uuid);
	daf_mode = settings:get('chazara', 'daf_mode', 'boolean');

    if daf_mode == nil or string.len(daf_mode) == 0 then
        daf_mode = "false";
    end
    chumash_mode = settings:get('chazara', 'chumash_mode', 'boolean');

    if chumash_mode == nil or string.len(chumash_mode) == 0 then
        chumash_mode = "false";
    end

--File exists function
function file_exists(name)
   local f=io.open(name,"r")
   if f~=nil then io.close(f) return true else return false end
end

function insert_cdr_record(recording_uuid, teacher_uuid, daf_teacher_uuid, start_epoch)
    local sql = "INSERT INTO v_chazara_cdrs (chazara_recording_uuid, domain_uuid, chazara_teacher_uuid, chazara_daf_teacher_uuid, call_uuid, start_epoch, "; 
    sql = sql .. "duration, caller_id_number, caller_id_name) "
    sql = sql .. "values (:chazara_recording_uuid, :domain_uuid, :chazara_teacher_uuid, :chazara_daf_teacher_uuid, :uuid, :start_epoch, :duration, :caller_id_number, :caller_id_name)";

    if (daf_teacher_uuid == nil or not is_uuid(daf_teacher_uuid)) then
        daf_teacher_uuid = dbh.NULL
    end

    local params = {
        chazara_recording_uuid = recording_uuid,
        domain_uuid = domain_uuid,
        chazara_teacher_uuid = teacher_uuid,
        chazara_daf_teacher_uuid = daf_teacher_uuid,
        uuid = uuid,
        start_epoch = start_epoch,
        caller_id_number = caller_id_number,
        caller_id_name = caller_id_name,
        duration = os.time() - start_epoch
    }
    dbh:query(sql, params);
end

function save_bookmark(teacher_uuid, filename)
    -- Insert into hash for later playback
    local playback_last_offset_pos = session:getVariable("playback_last_offset_pos");
    if file_exists(recordings_dir .. teacher_uuid .. "/" .. filename) then
        local soxi_handle = io.popen('soxi -s ' .. recordings_dir .. teacher_uuid .. "/" .. filename)
        local output = soxi_handle:read('*a')
        file_total_samples = tonumber(output);
        soxi_handle:close()
    end
    if tonumber(playback_last_offset_pos) ~= nil and tonumber(file_total_samples) ~= nil 
        and tonumber(playback_last_offset_pos) < (tonumber(file_total_samples) * .9) then
        freeswitch.consoleLog("INFO", "Last playback position was " .. playback_last_offset_pos .. "\n");
        session:execute("hash", "insert/" .. domain_uuid .. "_bookmark/" .. caller_id_number .. "/" .. filename .. ":" .. playback_last_offset_pos);
    else
        api:execute("hash", "delete/" .. domain_uuid .. "_bookmark/" .. caller_id_number);
    end
end

function play_file(teacher_uuid, filename, recording_uuid, daf_teacher_uuid, offset)
    local start_epoch = os.time();
    -- Play file
    session:setInputCallback("cpb_dtmf_input", "");
    session:streamFile(recordings_dir .. teacher_uuid .. "/" .. filename, offset);
    session:unsetInputCallback();

    insert_cdr_record(recording_uuid, teacher_uuid, daf_teacher_uuid, start_epoch)
    save_bookmark(teacher_uuid, filename)
end

function check_for_next_recording(recording_uuid)
    local fields, conditions, order
    local result = nil

    if (daf_mode == "true") then
        fields = "daf_number, daf_amud, daf_end_line"
        conditions = [[
            (vcr.daf_number = (SELECT daf_number FROM cur) AND vcr.daf_amud = (SELECT daf_amud FROM cur) AND vcr.daf_start_line = (SELECT daf_end_line FROM cur) + 1)
            OR (vcr.daf_number = (SELECT daf_number FROM cur) AND (SELECT daf_amud FROM cur) = 'a' AND vcr.daf_amud = 'b')
            OR vcr.daf_number = (SELECT daf_number FROM cur) + 1
        ]]
        order = "vcr.daf_number, vcr.daf_amud, vcr.daf_start_line"
    elseif (chumash_mode == "true") then
        fields = "chumash_end_chapter, chumash_end_verse"
        conditions = [[
            (vcr.chumash_start_chapter = (SELECT chumash_end_chapter FROM cur) AND vcr.chumash_start_verse = (SELECT chumash_end_verse FROM cur) + 1)
            OR (vcr.chumash_start_chapter = (SELECT chumash_end_chapter FROM cur) + 1 AND vcr.chumash_start_verse = 1)
        ]]
        order = "vcr.chumash_start_chapter, vcr.chumash_start_verse"
    else
        fields = "recording_id"
        conditions = "vcr.recording_id = (SELECT recording_id FROM cur) + 1"
        order = "vcr.recording_id"
    end

    local sql = [[
        WITH cur AS (
            SELECT chazara_teacher_uuid, chazara_daf_teacher_uuid, ]] .. fields .. [[
            FROM v_chazara_recordings
            WHERE chazara_recording_uuid = :recording_uuid
        )
        SELECT chazara_recording_uuid, recording_filename FROM v_chazara_recordings vcr
        WHERE domain_uuid = :domain_uuid
        AND vcr.chazara_teacher_uuid = (SELECT chazara_teacher_uuid FROM cur)
        AND vcr.chazara_daf_teacher_uuid = (SELECT chazara_daf_teacher_uuid FROM cur)
        AND (]] .. conditions .. [[)
        ORDER BY ]] .. order .. [[
        LIMIT 1
    ]]

    dbh:query(sql, {recording_uuid = recording_uuid}, function(row)
        result = {recording_uuid = row['chazara_recording_uuid'], recording_filename = row['recording_filename']}
    end);

    if (result ~= nil) then
        local confirm = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. "play_next.wav", "", "[12]");
        if (confirm == "1") then
            return result
        end
    end

    return nil
end

-- Chumash by parsha function
local function chumash_by_parsha(epoch)
    local cache_file = api:execute("http_get", "http://www.hebcal.com/hebcal?v=1&cfg=json&s=on&year=now&ss=on&start=" .. os.date("%Y-%m-%d", epoch) .. os.date("&end=%Y-%m-%d", epoch + 7*24*60*60));
    local file = io.open(cache_file, "r")
    if file then
        content = file:read("*all")
        file:close()
    else
        freeswitch.consoleLog("WARNING", "Error: Could not open file " .. cache_file)
        return;
    end
    local hebcal_response = json:decode(content);
    content = nil
    local tbl_cur_parsha = {}
    -- "torah":"Genesis 6:9-11:32"
    for item_list_no = 1, #hebcal_response['items'], 1 do
        -- We need to sift through other holiday information
        if hebcal_response['items'][item_list_no]['category'] == "parashat" then
            tbl_cur_parsha = split(hebcal_response['items'][item_list_no]['leyning']['torah'], ' ');
            break
        end
    end
    -- Figure out sefer
    local parallel_class_id = "";
    if tbl_cur_parsha[1] == "Genesis" then parallel_class_id = 1; end;
    if tbl_cur_parsha[1] == "Exodus" then parallel_class_id = 2; end;
    if tbl_cur_parsha[1] == "Leviticus" then parallel_class_id = 3; end;
    if tbl_cur_parsha[1] == "Numbers" then parallel_class_id = 4; end;
    if tbl_cur_parsha[1] == "Deuteronomy" then parallel_class_id = 5; end;

    local tbl_parsha_range = split(tbl_cur_parsha[2], "-");

    local tbl_parsha_start = split(tbl_parsha_range[1], ":")
    local parsha_start_chapter = tbl_parsha_start[1];
    local parsha_start_verse = tbl_parsha_start[2];
    local tbl_parsha_end = split(tbl_parsha_range[2], ":")
    local parsha_end_chapter = tbl_parsha_end[1];
    local parsha_end_verse = tbl_parsha_end[2];

    -- If there is more than one leyning, the parsha_end_verse will end with a ;
    -- "torah":"Genesis 41:1-44:17; Numbers 28:9-15, 7:42-47","haftarah":"Zechariah 2:14-4:7 | Shabbat Rosh Chodesh Chanukah"
    -- It needs to be stripped out
    if string.sub(parsha_end_verse, -1) == ";" or string.sub(parsha_end_verse, -1) == "," then
        parsha_end_verse = string.sub(parsha_end_verse, 1, -2);
    end

    -- Match parallel class to teacher_uuid
    local sql = [[SELECT chazara_teacher_uuid, pin FROM v_chazara_teachers
                WHERE domain_uuid = :domain_uuid
                AND grade = :grade
                AND parallel_class_id = :parallel]];
    local params = {
        domain_uuid = domain_uuid,
        grade = 1,
        parallel = parallel_class_id
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        chazara_teacher_uuid = row["chazara_teacher_uuid"];
    end);

    -- Get classes from database
    local sql = [[SELECT recording_filename, chazara_recording_uuid, chazara_daf_teacher_uuid
                FROM v_chazara_recordings
                WHERE domain_uuid = :domain_uuid
                AND chazara_teacher_uuid = :chazara_teacher_uuid
                AND ((chumash_start_chapter = :parsha_start_chapter AND chumash_start_verse >= :parsha_start_verse)
                OR (chumash_start_chapter > :parsha_start_chapter AND chumash_end_chapter < :parsha_end_chapter)
                OR (chumash_end_chapter = :parsha_end_chapter AND chumash_end_verse <= :parsha_end_verse))
                ORDER BY chumash_start_chapter asc, chumash_start_verse asc, chazara_daf_teacher_uuid asc]];
    local params = {
        domain_uuid = domain_uuid,
        chazara_teacher_uuid = chazara_teacher_uuid,
        parsha_start_chapter = parsha_start_chapter,
        parsha_end_chapter = parsha_end_chapter,
        parsha_start_verse = parsha_start_verse,
        parsha_end_verse = parsha_end_verse
    };
    local tbl_parsha_recording_files = {};
    local tbl_parsha_recording_uuid = {}
    dbh:query(sql, params, function(row)
        table.insert(tbl_parsha_recording_files, row['recording_filename']);
        table.insert(tbl_parsha_recording_uuid, row['chazara_recording_uuid']);
    end);

    local file_count = #tbl_parsha_recording_files;

    if file_count == 0 then
        session:streamFile(recordings_dir .. "recording_not_available.wav");
        return;
    else
        local prmpt_file = "file_string://" .. recordings_dir .. "there_are.wav!";
        prmpt_file = prmpt_file .. "digits/" .. tostring(file_count) .. ".wav!"
        prmpt_file = prmpt_file .. recordings_dir .. "this_week.wav";

        -- Loop
        local exit = false;
        while session:ready() and exit == false do
            local parsha_play_file = session:playAndGetDigits(1, string.len(tostring(#tbl_parsha_recording_files)), 3, 3000, "", prmpt_file, "", "");
            parsha_play_file = tonumber(parsha_play_file)
            if parsha_play_file == nil then
                exit = true;
            elseif parsha_play_file < 1 or parsha_play_file > file_count then
                session:streamFile(recordings_dir .. "invalid.wav");
            else
                play_file(chazara_teacher_uuid, tbl_parsha_recording_files[parsha_play_file], tbl_parsha_recording_uuid(parsha_play_file), nil, 0);
                -- TODO play next file
            end
        end
    end
end

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
                return;
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
        daf_recording = row["daf_recording"];
        amud_recording = row["amud_recording"];
        chazara_ivr_uuid = row["chazara_ivr_uuid"];
    end);
end

-- Play greeting pagd
::start_menu::
if session:ready() and greeting_recording ~= nil and string.len(greeting_recording) > 0 then
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
else
    caller_type = "1";
end

-- Transfer 8 to *732
if caller_type == "8" then
    session:execute("transfer", "*732 XML " .. domain_name);
end

-- Check for bookmark
    local last_file = api:execute("hash", "select/" .. domain_uuid .. "_bookmark/" .. caller_id_number);
    if last_file ~= nil and string.len(last_file) > 0 then
        split_last_file = split(last_file, ":");
        freeswitch.consoleLog("INFO", "Bookmark last file is " .. split_last_file[1] .. " and offset is " .. split_last_file[2]);
        -- we want to prompt and play file by uuid
        local play_bookmark = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. "play_bookmark.wav", "", "[12]");
        if play_bookmark == "1" then
            local sql = [[SELECT recording_filename, chazara_daf_teacher_uuid, chazara_teacher_uuid
                    FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_recording_uuid = :chazara_recording_uuid
            ]]
            local params = {
                domain_uuid = domain_uuid,
                chazara_recording_uuid = split_last_file[1]
            }
            dbh:query(sql, params, function(row)
                recording_filename = row["recording_filename"];
                chazara_teacher_uuid = row["chazara_teacher_uuid"];
                chazara_daf_teacher_uuid = row["chazara_daf_teacher_uuid"];
            end);

            local next = {recording_uuid = split_last_file[1], recording_filename = recording_filename};
            play_file(chazara_teacher_uuid, next["recording_filename"], next["recording_uuid"], nil, split_last_file[2])

            while (next ~= nil) do
                next = check_for_next_recording(next["recording_uuid"])
                play_file(chazara_teacher_uuid, next["recording_filename"], next["recording_uuid"], nil, 0)
            end

            recording_filename = {};
            chazara_recording_uuid = {};
            chazara_daf_teacher_uuid = {};
            file_total_samples = nil;
        else
            api:execute("hash", "delete/" .. domain_uuid .. "_bookmark/" .. caller_id_number);
        end
    end

-- Play grade menu, first find max grade
    local sql = [[SELECT MAX(grade) as max_grade, COUNT(DISTINCT grade) as grade_count FROM v_chazara_teachers
            WHERE domain_uuid = :domain_uuid]];
    local params = {
        domain_uuid = domain_uuid,
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        max_grade = row["max_grade"];
        grade_count = row["grade_count"];
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
    if tonumber(grade_count) == 1 then
        -- There is only one grade, skip menu
        local sql = [[SELECT DISTINCT grade FROM v_chazara_teachers WHERE domain_uuid = :domain_uuid]];
        local params = {
            domain_uuid = domain_uuid,
        };
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params, function(row)
            grade = row["grade"];
        end);
    else
        grade = session:playAndGetDigits(1, grade_max_digits, 3, digit_timeout, "#", recordings_dir .. grade_recording, "", "");
    end
    if grade == "*" then goto start_menu; end;
    if tonumber(grade) ~= nil then
        -- Inspect database if that grade exists, and how many parallels
        local sql = [[SELECT count(chazara_teacher_uuid) as count, MAX(parallel_class_id) as max_parallel FROM v_chazara_teachers
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
            max_parallel = tonumber(row["max_parallel"]);
        end);
        if count == 1 then
            exit = true;
            local sql = [[SELECT chazara_teacher_uuid, pin FROM v_chazara_teachers
                    WHERE domain_uuid = :domain_uuid
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
                chazara_teacher_uuid = row["chazara_teacher_uuid"];
                pin = row["pin"];
            end);
        end
        if count > 1 then
            exit = true;
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
        if tonumber(max_parallel) ~= nil and tonumber(max_parallel) > 9 then
            parallel_max_digits = 2;
        else
            parallel_max_digits = 1;
        end
    end
    timeout = timeout + 1
    if timeout > 3 then
        session:hangup();
    end
end

-- NEW FOR CHUMASH MODE - select current parsha
if session:ready() and chumash_mode == "true" then
    local chumash_type = session:playAndGetDigits(1, 1, 3, 5000, "#", recordings_dir .. "parsha_or_perek.wav", "", "[123]");
    if chumash_type == "1" then
        chumash_by_parsha(os.time())
        goto grade_menu;
    elseif chumash_type == "2" then
        chumash_by_parsha(os.time() - (60*60*24*7))
        goto grade_menu;
    else
        -- Do nothing
    end
end


-- play parallel menu if exists
-- If we're in daf_mode/chumash_mode, the parallel is the masechta/chumash
if parallel_recording ~= nil and string.len(parallel_recording) > 0 then
    session:flushDigits();
    local exit = false;
    local timeout = 0;
    while (session:ready() and exit == false) do
        parallel = session:playAndGetDigits(1, parallel_max_digits, 3, 2500, "#", recordings_dir .. parallel_recording, "", "");
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

-- Daf-mode
if session:ready() and daf_mode == "true" then
    freeswitch.consoleLog("WARNING", "We are in daf_mode " .. daf_mode .. "\n")
    session:flushDigits();
    local exit = false;
    local timeout = 0;
    while (session:ready() and exit == false) do
        daf = session:playAndGetDigits(1, 3, 3, 2500, "#", recordings_dir .. daf_recording, "silence_stream://500", "");
        if daf == "*" then goto grade_menu; end;
	daf = daf:match("0*(%d+)");
        if tonumber(daf) ~= nil then
            -- Validate that we have such a daf
            local sql = [[SELECT DISTINCT daf_number FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    and daf_number = :daf]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                daf = daf
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                daf_number = row["daf_number"];
            end);
            if daf_number ~= nil and string.len(daf_number) > 0 then
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

    local exit = false;
    local timeout = 0;
    while (session:ready() and exit == false) do
        amud = session:playAndGetDigits(1, 1, 3, digit_timeout, "#", recordings_dir .. amud_recording, "", "[12*]");
        if amud == "*" then goto grade_menu; end;
        if tonumber(amud) ~= nil then
            local sql = [[SELECT DISTINCT daf_amud FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND daf_number = :daf
                    AND daf_amud = :amud]];
	    if amud == "1" then amud = "a"; end
	    if amud == "2" then amud = "b"; end
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                daf = daf,
                amud = amud
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                daf_amud = row["daf_amud"];
            end);
            if daf_amud ~= nil and string.len(daf_amud) > 0 then
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
--End Daf Daf-mode

--Chumash mode
if session:ready() and chumash_mode == "true" then
    freeswitch.consoleLog("WARNING", "We are in chumash_mode " .. chumash_mode .. "\n")
    session:flushDigits();
    local exit = false;
    local timeout = 0;
    while (session:ready() and exit == false) do
        perek = session:playAndGetDigits(1, 3, 3, 2500, "#", recordings_dir .. daf_recording, "silence_stream://500", "");
        if perek == "*" then goto grade_menu; end;

	    perek = perek:match("0*(%d+)");
        if tonumber(perek) ~= nil then
            -- Validate that we have such a perek
            local sql = [[SELECT COUNT(*) FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
		            AND chumash_start_chapter <= :perek
                    AND chumash_end_chapter >= :perek]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
		        perek = perek
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(row)
                count = row["count"];
            end);
            if count ~= nil and tonumber(count) > 0 then
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
        if daf_mode == "true" then
            recording_id = session:playAndGetDigits(1, 2, 4, 2500, "#", recordings_dir .. "student_select_line.wav", "silence_stream://500", "");
        elseif chumash_mode == "true" then
            recording_id = session:playAndGetDigits(1, 2, 4, 2500, "#", recordings_dir .. "student_select_verse.wav", "silence_stream://500", "");
        else
            recording_id = session:playAndGetDigits(3, 3, 3, digit_timeout + 3000, "#", recordings_dir .. "student_select_class.wav", recordings_dir .. "invalid.wav", "");
        end
        if tonumber(recording_id) == nil then
            goto grade_menu
            break
        else
        -- Find recording
        if daf_mode == "true" then
            local sql = [[
                WITH cte AS (
                    SELECT recording_filename, chazara_recording_uuid, chazara_daf_teacher_uuid,
                        ROW_NUMBER() over (PARTITION BY chazara_daf_teacher_uuid ORDER BY daf_number DESC, daf_amud DESC, daf_start_line DESC) AS row 
                    FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND (
                        (daf_number = :daf AND daf_amud = :amud AND daf_start_line <= :start_line AND daf_end_line >= :start_line)
                        OR (daf_number = :daf AND :amud = 'b' AND daf_amud = 'a')
                        OR (:amud = 'a' AND daf_number = :daf - 1 AND daf_amud = 'b')
                    )
                )
                SELECT recording_filename, chazara_recording_uuid, chazara_daf_teacher_uuid FROM cte WHERE row = 1;
            ]]
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                daf = daf,
                amud = amud,
		        start_line = recording_id
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end

            dbh:query(sql, params, function(row)
                if row["recording_filename"] ~= nil and string.len(row["recording_filename"]) > 0 then
                    table.insert(recording_filename, row["recording_filename"]);
                    table.insert(chazara_recording_uuid, row["chazara_recording_uuid"]);
                    if row["chazara_daf_teacher_uuid"] ~= nil and is_uuid(row["chazara_daf_teacher_uuid"]) then
                        table.insert(chazara_daf_teacher_uuid, row["chazara_daf_teacher_uuid"])
                    else
                        table.insert(chazara_daf_teacher_uuid, "aad5fc46-ebbb-4a10-b667-3e6a5d51104f");
                    end
                end
            end);
        elseif chumash_mode == "true" then
            -- This probably needs to be fixed because if we have 52:1-53:5, 53:6-54:2, and 54:3-8, we'll have a prob
            local sql = [[SELECT recording_filename, chazara_recording_uuid, chazara_daf_teacher_uuid
                    FROM v_chazara_recordings
                    WHERE domain_uuid = :domain_uuid
                    AND chazara_teacher_uuid = :chazara_teacher_uuid
                    AND chumash_start_chapter <= :perek
                    AND chumash_end_chapter >= :perek
		            AND chumash_start_verse <= :recording_id
                    AND chumash_end_verse >= :recording_id
                    ORDER BY chumash_start_verse desc, chazara_daf_teacher_uuid asc]];
            local params = {
                domain_uuid = domain_uuid,
                chazara_teacher_uuid = chazara_teacher_uuid,
                perek = perek,
		        recording_id = recording_id
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[chazara_program] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end

            dbh:query(sql, params, function(row)
                if row["recording_filename"] ~= nil and string.len(row["recording_filename"]) > 0 then
                    table.insert(recording_filename, row["recording_filename"]);
                    table.insert(chazara_recording_uuid, row["chazara_recording_uuid"]);
                    if row["chazara_daf_teacher_uuid"] ~= nil and is_uuid(row["chazara_daf_teacher_uuid"]) then
                        table.insert(chazara_daf_teacher_uuid, row["chazara_daf_teacher_uuid"])
                    else
                        table.insert(chazara_daf_teacher_uuid, "aad5fc46-ebbb-4a10-b667-3e6a5d51104f");
                    end
                end
            end);
        else
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
                table.insert(recording_filename, row["recording_filename"]);
                table.insert(chazara_recording_uuid, row["chazara_recording_uuid"]);
            end);
        end

        -- Need to parse table
            if #recording_filename == 1 then
                local next = {recording_uuid = chazara_recording_uuid[1], recording_filename = recording_filename[1]}
                while (next ~= nil) do
                    play_file(chazara_teacher_uuid, next["recording_filename"], next["recording_uuid"], chazara_daf_teacher_uuid[1], 0)
                    next = check_for_next_recording(next["recording_uuid"])
                end

                recording_filename = {};
                chazara_recording_uuid = {};
                chazara_daf_teacher_uuid = {};
                file_total_samples = nil;
            elseif #recording_filename > 1 then
                -- Create a menu to select the rebbe
                local chazara_daf_teacher_recording = {}
                -- Get the rebbe recording names
                local sql = "SELECT * FROM v_chazara_daf_teachers WHERE chazara_daf_teacher_uuid IN ('"
                for i = 1, #chazara_daf_teacher_uuid, 1 do
                    sql = sql .. chazara_daf_teacher_uuid[i]
                    if i == #chazara_daf_teacher_uuid then
                        sql = sql .. "')"
                    else
                        sql = sql .. "','"
                    end
                end
                dbh:query(sql, {}, function(row)
                    if string.len(row['name_recording_path']) > 0 then
                        chazara_daf_teacher_recording[row['chazara_daf_teacher_uuid']] = row['name_recording_path']
                    end
                end);
                -- Build filestring
                local fs_rebbe = "file_string://"
                for i = 1, #chazara_daf_teacher_uuid, 1 do
                    fs_rebbe = fs_rebbe .. recordings_dir .. "for_rabbi.wav!";
                    if chazara_daf_teacher_recording[chazara_daf_teacher_uuid[i]] ~= nil then
                        fs_rebbe = fs_rebbe .. recordings_dir .. chazara_daf_teacher_recording[chazara_daf_teacher_uuid[i]] .. "!";
                    else
                        fs_rebbe = fs_rebbe .. "digits/" .. i .. ".wav!";
                    end
                    fs_rebbe = fs_rebbe .. recordings_dir .. "press.wav!";
                    fs_rebbe = fs_rebbe .. "digits/" .. i .. ".wav";
                    if i < #chazara_daf_teacher_uuid then fs_rebbe = fs_rebbe .. "!silence_stream://500!"; end;
                end          

                local select_recording = tonumber(session:playAndGetDigits(1,1,1,3000, "#", fs_rebbe, "", "\\d+"))
                if select_recording ~= nil and select_recording > 0 and select_recording <= #chazara_daf_teacher_uuid then
                    local next = {recording_uuid = chazara_recording_uuid[select_recording], recording_filename = recording_filename[select_recording]}
                    while (next ~= nil) do
                        play_file(chazara_teacher_uuid, next["recording_filename"], next["recording_uuid"], chazara_daf_teacher_uuid[select_recording], 0)
                        next = check_for_next_recording(next["recording_uuid"])
                    end

                    -- Continue play to next recording
                    -- Things to evaluate
                    if session:ready() then
                        if daf_mode == "false" and chumash_mode == "false" then
                            -- Increment the recording ID by 1 and check if it exists

                        elseif daf_mode == "true" then
                            -- Check daf_end line and current rebbi - increment end line by one, check if recording exists
                                -- if not, icrement amud and/or daf, reset line to 1, and check again
                        elseif chumash_mode == "true" then
                            -- If we're playing by parsha, go to the next number in the list (don't know yet how)
                            -- If by perek/pasuk, then increment last pasuk by one and test
                                -- if not, increment perek by 1 and pasuk back to 1 and test
                        end
                    end

                    -- Clear these until we stop playback
                    recording_filename = {};
                    chazara_recording_uuid = {};
                    chazara_daf_teacher_uuid = {};
                    file_total_samples = nil;
                else
                    session:streamFile(recordings_dir .. "invalid.wav");
                end
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
            while (incomplete and timeout < 3) do

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

                    -- Get file length
                    local result = io.popen('soxi -D ' .. recordings_dir .. "/" .. chazara_teacher_uuid .. "/" .. recording_filename);
                    result:flush();
                    local length = result:read('*n');
                    result:close();

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
                        length = length
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
        recording_id = session:playAndGetDigits(3, 3, 3, digit_timeout + 3000, "#", recordings_dir .. "teacher_select_class.wav", recordings_dir .. "invalid.wav", "");
        if tonumber(recording_id) == nil then
            goto grade_menu
            break
        elseif recording_id == "000" then
            -- Change password
            local new_password = session:playAndGetDigits(4, 7, 3, 5000, "#", recordings_dir .. "choose_password.wav", recordings_dir .. "invalid.wav", "\\d+");
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
