-- hotline_story.lua
--
-- This file belongs to a standalone project
-- by the Circle to collect stories, play back,
-- and vote.
--
-- (c) 2022 The Voice of Lakewood, Circle Magazine
-- and Joseph Nadiv <ynadiv@corpit.xyz>
require "resources.functions.config";
audio_dir = "/usr/share/freeswitch/sounds/the_circle/top_ten_hotline/"
debug.sql = true;
json = freeswitch.JSON();
-- require "app.the_loop.applications.record_to_upload";

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

function save_vote(vote)
    if (customer_id == nil) then
        local sql = "INSERT INTO circle_customer (caller_id_number, caller_id_name)";
        sql = sql .. " values (:caller_id_number, :caller_id_name); ";
        local params = {
            caller_id_name = caller_id_name,
            caller_id_number = caller_id_number
        }
        if (debug["sql"]) then
            freeswitch.consoleLog("notice", "[loop_story] SQL: " .. sql .. "; params:" .. json:encode(params) .. "\n");
        end
        dbh:query(sql, params);
        -- get the customer id
        local sql = "SELECT customer_id FROM circle_customer WHERE caller_id_number = :caller_id_number";
        local params = {
            caller_id_number = caller_id_number
        }
        dbh:query(sql, params, function(row)
            customer_id = row.customer_id;
        end);
    end
    -- save the vote
    local sql = "INSERT INTO circle_tt_votes (customer_id, vote, call_uuid)"
    sql = sql .. " values (:customer_id, :vote, :uuid)";
    local params = {
        customer_id = customer_id,
        vote = vote,
        uuid = uuid
    }
    dbh:query(sql, params);
    -- Play confirmation
    session:streamFile(audio_dir .. "top_ten_goodbye.wav");
    story_incomplete = 0;
end

-- set the defaults
digit_max_length = 3;
timeout_pin = 5000;
max_tries = 3;
digit_timeout = 5000;
max_len_seconds = 15;
story_incomplete = 1;
voicemail_id = "250";

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
local sql = "select customer_id from circle_customer WHERE caller_id_number = :caller_id_number; ";
local params = {
    caller_id_number = caller_id_number
};
if (debug["sql"]) then
    freeswitch.consoleLog("notice", "[directory] SQL: " .. sql .. "; params: " .. json:encode(params) .. "\n");
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

if (session:ready()) then
    -- play main menu and instructions
    ::start_vote::
    vote_dtmf_digits = session:playAndGetDigits(1, 1, 5, digit_timeout, "#", audio_dir .. "top_ten_main_vote.wav", "",
        "");
    if (vote_dtmf_digits ~= nil and vote_dtmf_digits == "1") then
        vote_dtmf_digits = vote_dtmf_digits .. session:getDigits(1, "#", 3000);
    end
    if (tonumber(vote_dtmf_digits) == nil) then
        session:hangup();
    end

    if (session:ready() and tonumber(vote_dtmf_digits) < 1 or tonumber(vote_dtmf_digits) > 10) then
        goto start_vote
    end

    -- record vM    
    if (session:ready()) then
        session:setInputCallback("on_dtmf", "");
        dtmf_digits = session:playAndGetDigits(0, 1, 1, 500, "#", audio_dir .. "top_ten_record_info.wav", "", "\\d+")
        dtmf_digits = '';
        session:execute("playback", "silence_stream://200");
        session:streamFile("tone_stream://L=1;%(500, 0, 640)");
        start_epoch = os.time();
        result = session:recordFile(voicemail_dir .. "/" .. voicemail_id .. "/msg_" .. uuid .. ".wav", max_len_seconds,
            500, 4);
        message_length = (os.time() - start_epoch);
        session:unsetInputCallback();

    end

    if tonumber(message_length) > 3 then
        result = save_vm()
        if result then
            save_vote(vote_dtmf_digits);
        end
    end

    session:hangup();

end

--[[
DROP TABLE circle_customer CASCADE;
DROP TABLE circle_tt_votes CASCADE;

CREATE TABLE circle_customer(
customer_id SERIAL PRIMARY KEY,
caller_id_number VARCHAR(15) UNIQUE NOT NULL,
caller_id_name VARCHAR(255)
);

CREATE TABLE circle_tt_votes(
customer_id INTEGER NOT NULL,
vote SMALLINT NOT NULL,
CONSTRAINT fk_customer_id
FOREIGN KEY(customer_id)
REFERENCES circle_customer(customer_id)
);

Also needed is to import the prompt files into audio_dir
and into en/us/ferber
modify /etc/freeswitch/languages/en.xml to use ferber

]]
