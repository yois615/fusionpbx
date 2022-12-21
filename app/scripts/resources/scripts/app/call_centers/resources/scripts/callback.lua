--[[
Steps:
function to collect userinfo.  Needs to take channel vars and DB vars
    so that we know who to call back and how to call them

looping function to monitor queue cc-base-score
    if the top user has a base score lower than my score (using os.time - start_epoch), and caller is not currently in queue, then call back and add to queue with my cc-base-score
        If noone in queue then callback
            
Need to figure out how this plays with abandoned

        freeswitch@fusion.corpit.xyz> callcenter_config queue list members 60001@fusion.corpit.xyz
queue|instance_id|uuid|session_uuid|cid_number|cid_name|system_epoch|joined_epoch|rejoined_epoch|bridge_epoch|abandoned_epoch|base_score|skill_score|serving_agent|serving_system|state|score
60001@fusion.corpit.xyz|single_box|5cfc0fee-3c95-4809-a32c-41379ade5f74|85c4cb75-e0fe-42ee-8203-ab501c11ad69|14013452580|WIRELESS CALLER|1671466072|1671466077|0|0|0|105|0|||Waiting|105
+OK


function to call back user using gateway and DB vars for caller ID


Need to create table with domain, which queue in, position, entry time, how many attempts to call back
Need a UI to set up callback options

]] 

api = freeswitch.API()
action = argv[1]
queue_uuid = argv[2]

if (action == nil or queue_id == nil) then
    return;
end

-- get the variables
domain_name = session:getVariable("domain_name");
caller_id_name = session:getVariable("caller_id_name");
caller_id_number = session:getVariable("caller_id_number");
domain_uuid = session:getVariable("domain_uuid");
uuid = session:getVariable("uuid");

-- include config.lua
require "resources.functions.config";

-- load libraries
local Database = require "resources.functions.database";
dbh = Database.new('system');
local Settings = require "resources.functions.lazy_settings";
local file = require "resources.functions.file";

-- get the sounds dir, language, dialect and voice
local sounds_dir = session:getVariable("sounds_dir");
local default_language = session:getVariable("default_language") or 'en';
local default_dialect = session:getVariable("default_dialect") or 'us';
local default_voice = session:getVariable("default_voice") or 'callie';

-- get the recordings settings
local settings = Settings.new(db, domain_name, domain_uuid);

-- set the storage type and path
storage_type = settings:get('recordings', 'storage_type', 'text') or '';
storage_path = settings:get('recordings', 'storage_path', 'text') or '';
if (storage_path ~= '') then
    storage_path = storage_path:gsub("${domain_name}", session:getVariable("domain_name"));
    storage_path = storage_path:gsub("${domain_uuid}", domain_uuid);
end
-- set the recordings directory
local recordings_dir = recordings_dir .. "/" .. domain_name;

-- Get the callback_profile
local sql = "SELECT c.queue_extension, p.caller_id_number, p.caller_id_name, p.callback_dialplan, p.callback_request_prompt, "
sql = sql .. "p. callback_confirm_prompt, p.callback_force_cid, p.callback_retries, p.callback_timeout, p.callback_retry_delay "
sql = sql .. "FROM v_call_center_queues c INNER JOIN v_call_center_callback_profile p ON c.queue_callback_profile = p.id ";
sql = sql .. "WHERE c.call_center_queue_uuid = :queue_uuid";
local params = {
    queue_uuid = queue_uuid
};
local queue_details = dbh:query(sql, params, function(row)
    queue_extension = row.queue_extension;
    callback_cid_number = row.caller_id_number;
    callback_cid_name = row.caller_id_name;
    callback_dialplan = row.callback_dialplan;
    callback_request_prompt = row.callback_request_prompt;
    callback_confirm_prompt = row.callback_confirm_prompt;
    callback_force_cid = row.callback_force_cid;
    callback_retries = row.callback_retries;
    callback_timeout = row.callback_timeout;
    callback_retry_delay = row.callback_retry_delay;
end);

-- Initial callback request
if (action == "start") then
    
    if (string.len(callback_request_prompt) > 0) then
        if (file_exists(recordings_dir .."/" .. callback_request_prompt)) then
        session:streamFile(recordings_dir .."/" .. callback_request_prompt);
        else
            session:streamFile(callback_request_prompt);
        end
    else
        --Play some default annoucnement
    end
    session:say(caller_id_number, "en", "telephone_number", "iterated");
    if (callback_force_cid == false) then
        -- To accept this number press 1, to enter a different number press 2
        local dtmf_digits = session:playAndGetDigits(1, 1, 3, 3000, "#", sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/ivr/ivr-accept_reject_voicemail.wav", "", "[12]")
        if (dtmf_digits ~= nil and dtmf_digits == "2") then
            invalid = 0;
            valid = false;
            while (session:ready() and invalid < 3 and valid == false) do
                caller_id_number = session:playAndGetDigits(10, 14, 3, 3000, "#", "enter_your_number.wav", "", "\\d+");
                local valid_callback = api:execute("regex", caller_id_number .. "|" .. callback_dialplan);
                if (valid_callback == "true") then
                    valid = true;
                end
                invalid = invalid + 1;
            end
            if valid == false and (dtmf_digits == nil or dtmf_digits == 2) then
                session:execute("transfer", queue_extension .. " XML " .. domain_name);
                return;
            end
        end
    end
    local dtmf_digits = session:playAndGetDigits(1, 1, 3, 3000, "#", sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/ivr/ivr-accept_reject_voicemail.wav", "", "[12]");
        if (dtmf_digits ~= nil and dtmf_digits == "1") then
            --TODO put call in table, play confirmation, and hangup
            local joined_epoch = session:getVariable("cc_queue_joined_epoch");
            sql = "INSERT INTO v_call_center_callbacks (call_center_queue_uuid, domain_uuid, ";
            sql = sql .. "call_uuid, start_epoch, caller_id_name, caller_id_number, retry_count, status) ";
            sql = sql .. "VALUES (:queue_uuid, :domain_uuid, :uuid, :cc_queue_joined_epoch, :caller_id_name, "
            sql = sql .. "caller_id_number, 0, 'pending' ";
            local params = {
                queue_uuid = queue_uuid,
                domain_uuid = domain_uuid,
                uuid = uuid,
                cc_queue_joined_epoch = cc_queue_joined_epoch,
                caller_id_name = caller_id_name,
                caller_id_number = caller_id_number                    
            }
            dbh:query(sql, params);
            session:hangup();
        else
            session:execute("transfer", queue_extension .. " XML " .. domain_name);
        end
end
-- digit = session:playAndGetDigits(min_digits, max_digits, max_tries, digit_timeout, "#", sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/ivr/ivr-accept_reject_voicemail.wav", "", "\\d+")
-- cc_queue_canceled_epoch

if action == "event" then
    -- Process all the callbacks and originate calls
end
