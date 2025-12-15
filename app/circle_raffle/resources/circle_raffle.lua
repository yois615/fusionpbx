-- hotline_story.lua
--
-- This file belongs to a standalone project
-- by the Circle to collect stories, play back,
-- and vote.
--
-- (c) 2022 The Voice of Lakewood, Circle Magazine
-- and Joseph Nadiv <ynadiv@corpit.xyz>
require "resources.functions.config";
require "resources.functions.mkdir";
audio_dir = "/usr/share/freeswitch/sounds/the_circle/raffle/"
debug.sql = true;
json = freeswitch.JSON();

-- connect to the database
local Database = require "resources.functions.database";
dbh = Database.new('system');

-- functions
function on_dtmf(s, type, obj, arg)
    return 0;
end

function save_vm()
    domain_name = session:getVariable("domain_name");
    domain_uuid = session:getVariable("domain_uuid");
    if (domain_uuid == nil) then
        if (domain_name ~= nil) then
            local sql = "SELECT domain_uuid FROM v_domains ";
            sql = sql .. "WHERE domain_name = :domain_name ";
            local params = {
                domain_name = domain_name
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[voicemail] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params, function(rows)
                domain_uuid = rows["domain_uuid"];
            end);
        end
    end
    if (domain_uuid ~= nil) then
        domain_uuid = string.lower(domain_uuid);
    end
    local sql = [[SELECT * FROM v_voicemails
							WHERE domain_uuid = :domain_uuid
							AND voicemail_id = :voicemail_id
							AND voicemail_enabled = 'true' ]];
    local params = {
        domain_uuid = domain_uuid,
        voicemail_id = voicemail_id
    };
    if (debug["sql"]) then
        freeswitch.consoleLog("notice", "[voicemail] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
    end
    dbh:query(sql, params, function(row)
        voicemail_uuid = string.lower(row["voicemail_uuid"]);
        voicemail_password = row["voicemail_password"];
        greeting_id = row["greeting_id"];
        voicemail_alternate_greet_id = row["voicemail_alternate_greet_id"];
        voicemail_mail_to = row["voicemail_mail_to"];
        voicemail_attach_file = row["voicemail_attach_file"];
        voicemail_local_after_email = row["voicemail_local_after_email"];
        voicemail_transcription_enabled = row["voicemail_transcription_enabled"];
        voicemail_tutorial = row["voicemail_tutorial"];
    end);

    if (tonumber(message_length) > 2) then
        caller_id_name = string.gsub(caller_id_name, "'", "''");
        local sql = {}
        table.insert(sql, "INSERT INTO v_voicemail_messages ");
        table.insert(sql, "(");
        table.insert(sql, "voicemail_message_uuid, ");
        table.insert(sql, "domain_uuid, ");
        table.insert(sql, "voicemail_uuid, ");
        table.insert(sql, "created_epoch, ");
        table.insert(sql, "caller_id_name, ");
        table.insert(sql, "caller_id_number, ");
        table.insert(sql, "message_length ");
        table.insert(sql, ") ");
        table.insert(sql, "VALUES ");
        table.insert(sql, "( ");
        table.insert(sql, ":voicemail_message_uuid, ");
        table.insert(sql, ":domain_uuid, ");
        table.insert(sql, ":voicemail_uuid, ");
        table.insert(sql, ":start_epoch, ");
        table.insert(sql, ":caller_id_name, ");
        table.insert(sql, ":caller_id_number, ");
        table.insert(sql, ":message_length ");
        table.insert(sql, ") ");
        sql = table.concat(sql, "\n");
        local params = {
            voicemail_message_uuid = voicemail_message_uuid,
            domain_uuid = domain_uuid,
            voicemail_uuid = voicemail_uuid,
            start_epoch = start_epoch,
            caller_id_name = caller_id_name,
            caller_id_number = caller_id_number,
            message_base64 = message_base64,
            transcription = transcription,
            message_length = message_length
            -- message_status = message_status;
            -- message_priority = message_priority;
        };
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[voicemail] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params);
        return true;
    end

    -- define uuid function
    local random = math.random;
    local function gen_uuid()
        local template = 'xxxxxxxx-xxxx-bxxx-yxxx-xxxxxxxxxxxx';
        return string.gsub(template, '[xy]', function(c)
            local v = (c == 'x') and random(0, 0xf) or random(8, 0xb);
            return string.format('%x', v);
        end)
    end
end

-- set the defaults
digit_max_length = 3;
timeout_pin = 5000;
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;
story_incomplete = 1;
voicemail_id = 375;

-- get session variables
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
uuid = session:getVariable("uuid");
voicemail_message_uuid = uuid;
voicemail_dir = "/var/lib/freeswitch/storage/voicemail/default/the-circle.corpit.xyz";

-- Strip E.164 plus sign
if (string.sub(caller_id_number, 1, 1) == "+") then
    caller_id_number = string.sub(caller_id_number, 2);
end

-- Check if any recordings associated with this phone number
local sql = "select customer_id from circle_raffle_customer WHERE caller_id_number = :caller_id_number; ";
local params = {
    caller_id_number = caller_id_number
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice", "[circle_raffle] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
end
dbh:query(sql, params, function(row)
    customer_id = tonumber(row.customer_id);
end)

if (session:ready()) then
    -- answer the call
    session:answer();
    -- Insert delay so that we hear the first words
    session:execute("playback", "silence_stream://200");
end

-- Reject bad callerID
if (string.len(caller_id_number) < 10 or tonumber(caller_id_number) == nil) then
    session:streamFile(audio_dir .. "bad_caller_id.wav");
    session:hangup();
end

-- Check how many times they called
local sql = "select count(call_uuid) from circle_raffle_cdr WHERE customer_id = :customer_id and call_epoch > :os_time; ";
local params = {
    customer_id = customer_id,
    os_time = os.time() - 86400
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice", "[circle_raffle] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
end
dbh:query(sql, params, function(row)
    if row.count > 3 then
        session:streamFile(audio_dir .. "try_again_tomorrow.wav");
        session:hangup();
    end
end)

if (session:ready()) then
    -- Play greeting without interruption


-- Type in your number
number_picked = session:playAndGetDigits(5, 5, 2, 3000, "#", audio_dir .. "raffle_greeting.wav", "", "");

-- Is that number available?
local sql = "select count(winning_number) from circle_raffle_numbers WHERE winning_number = :number_picked and call_epoch IS NULL or call_epoch = '' ";
local params = {
    number_picked = number_picked
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice", "[circle_raffle] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
end
dbh:query(sql, params, function(row)
    if row.count == "0" then
        session:streamFile(audio_dir .. "you_lost.wav");
        local sql = "INSERT INTO circle_raffle_cdr (customer_id, call_epoch) VALUES (:customer_id, :call_epoch); ";
        local params = {
            customer_id = customer_id,
            call_epoch = os.time()
        };
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[circle_raffle] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params)
        session:hangup();
    end
end)

    -- record vM    
    if (session:ready()) then
        -- WE HAVE A WINNER!
        session:streamFile(audio_dir .. "you_won.wav");
        session:setInputCallback("on_dtmf", "");
        dtmf_digits = session:playAndGetDigits(0, 1, 1, 500, "#", audio_dir .. "raffle_winner_vm.wav", "", "\\d+")
        dtmf_digits = '';
        session:execute("playback", "silence_stream://200");
        session:streamFile("tone_stream://L=1;%(500, 0, 640)");
        start_epoch = os.time();
        --make sure the voicemail_dir exists
	    mkdir(voicemail_dir .. "/" .. voicemail_id);
        result = session:recordFile(voicemail_dir .. "/" .. voicemail_id .. "/msg_" .. uuid .. ".wav", max_len_seconds,
            500, 4);
        message_length = (os.time() - start_epoch);
        session:unsetInputCallback();

    end

    if tonumber(message_length) > 3 then
        result = save_vm()
        if result then
            local sql = "UPDATE circle_raffle_numbers SET winning_customer_id = customer_id; call_epoch = call_epoch, call_uuid = call_uuid WHERE winning_number = :number_picked ";
            local params = {
                customer_id = customer_id,
                call_epoch = os.time(),
                call_uuid = uuid
            };
            if (debug["sql"]) then
                freeswitch.consoleLog("notice", "[circle_raffle] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
            end
            dbh:query(sql, params)
        end
    end
    session:hangup();

end
