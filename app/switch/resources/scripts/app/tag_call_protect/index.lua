--TAG-Call-Protect
-- 
-- A script to block AI chat numbers and news hotlines using TAG's ECHCallBlocker API
-- Need to get API key from domain vars
api = freeswitch.API()
json = require "resources.functions.lunajson"

local Database = require "resources.functions.database"
local Settings = require "resources.functions.lazy_settings"

local domain_uuid = session:getVariable("domain_uuid");
local domain_name = session:getVariable("domain_name");
local call_direction = session:getVariable("call_direction");
local recordings_dir = session:getVariable("recordings_dir");

session:setVariable("curl_timeout", "6")

local debug_api = true;

local function escape (str)
    str = string.gsub (str, "([^0-9a-zA-Z !'()*._~-])", -- locale independent
       function (c) return string.format ("%%%02X", string.byte(c)) end)
    str = string.gsub (str, " ", "%%20")
    return str
end

local function dump_table(o)
	if type(o) == 'table' then
	   local s = '{ '
	   for k,v in pairs(o) do
		  if type(k) ~= 'number' then k = '"'..k..'"' end
		  s = s .. '['..k..'] = ' .. dump_table(v) .. ','
	   end
	   return s .. '} '
	else
	   return tostring(o)
	end
end

--connect to the database
local dbh = Database.new('system');
local settings = Settings.new(dbh, domain_name, domain_uuid);

local api_key = settings:get("tag_call_protect", "api_key", "text");
local callblocker_url = settings:get("tag_call_protect", "url", "text");
local str_lists = session:getVariable("tag_call_protect_lists")
if str_lists == nil or string.len(str_lists) == 0 then
    str_lists = "standard";
end
local str_customer = session:getVariable("tag_call_protect_customer");
if str_customer == nil or string.len(str_customer) == 0 then
    str_customer = "Unknown"
end
local lists = split(str_lists, ",", true);
local header_token = " append_headers 'X-API-Key: "..api_key.."' ";

    -- Need to determine call direction
    if session:ready() then
        local post_body = {};
        local suspect_number = session:getVariable("sip_to_user");
        if call_direction == "inbound" then
            suspect_number = session:getVariable("caller_id_number");
        end
        -- Ensure we don't block 911 or other service codes but allow 7 digit dialing
        if string.len(suspect_number) < 7 then
            -- Is this a short code or MDC?
            if string.sub(suspect_number, 1, 1) == "*" or string.sub(suspect_number, 1, 1) == "#" then
                -- Do nothing
            else
                -- We don't want to check against this number
                return
            end  
        end
        post_body.number = suspect_number
        post_body.lists = {}
        for _, list in ipairs(lists) do
            table.insert(post_body.lists, list);
        end
        post_body.requester = str_customer;

        hook_url = callblocker_url .. header_token.."content-type 'application/json' post ";

        -- TODO encode JSON data from table
        post_body = json.encode(post_body);

        session:execute("curl", hook_url .. escape(post_body));
        post_response = session:getVariable("curl_response_data");
        post_response_code = session:getVariable("curl_response_code")

        if post_response_code ~= "200" or post_response == nil or string.len(post_response) == 0 or string.sub(post_response, 1, 1) == "-" then
            freeswitch.consoleLog("ERR", "TAG-Call-Protect coded " .. post_response_code .. "\n");
            if post_response ~= nil then
                freeswitch.consoleLog("ERR", "TAG-Call-Protect data: " .. post_response .. "\n");
            end
            -- Let the call through
            return
        end

        tbl_post_response = json.decode(post_response);

        if (debug_api) then
            freeswitch.consoleLog("NOTICE", "TAG-Call-Protect response " .. dump_table(tbl_post_response) .. "\n");
        end

        if tbl_post_response['data']['status'] == "blocked" then
            freeswitch.consoleLog("INFO", "Call to " .. suspect_number .. " was rejected by TAG-Call-Protect\n")
            session:execute("media_reset", "")
            session:execute("pre_answer", "")
            session:execute("playback", recordings_dir .. "/" .. domain_name .. "/SIT-TAGCallProtect.wav");
            session:execute("respond", "403")
            session:hangup()
        end

    end


