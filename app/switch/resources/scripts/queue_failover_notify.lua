--usage
-- queue_failover_notify.lua {queue_name} {email_to} {transfer_to}

--load libraries
local send_mail = require 'resources.functions.send_mail'

domain_uuid = session:getVariable("domain_uuid");
domain_name = session:getVariable("domain_name");
uuid = session:getVariable("uuid");

-- Get arguments
local queue = argv[1]
local voicemail_mail_to = argv[2];
local transfer_to = argv[3];

--prepare the headers
local headers = {
    ["X-FusionPBX-Domain-UUID"] = domain_uuid;
    ["X-FusionPBX-Domain-Name"] = domain_name;
    ["X-FusionPBX-Call-UUID"]   = uuid;
    ["X-FusionPBX-Email-Type"]  = 'notification';
}

local subject = "NOTICE: Queue failover to answering service";
local body = "A call has failed over from queue " .. queue .. " to the answering service.";

send_mail(headers,
nil,
voicemail_mail_to,
{subject, body},
nil
);

session:transfer(transfer_to, 'XML', domain_name);