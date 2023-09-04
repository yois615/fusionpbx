-- call-wait-code
-- Calls a Number, Waits a Bit, Enters the Code
-- <action application="lua" data="call-wait-code.lua $call $wait $code"/>

--api:execute("create_uuid");

api = freeswitch.API();
require "resources.functions.trim";
require "resources.functions.format_seconds";
--connect to the database
        Database = require "resources.functions.database";
        dbh = Database.new('system');

--define uuid function
        local random = math.random;
        local function gen_uuid()
                local template ='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
                return string.gsub(template, '[xy]', function (c)
                        local v = (c == 'x') and random(0, 0xf) or random(8, 0xb);
                        return string.format('%x', v);
                end)
        end



--if the session exists
        if (session ~= nil) then

                --get session variables
                        context = session:getVariable("context");
                        sounds_dir = session:getVariable("sounds_dir");
                        domain_name = session:getVariable("domain_name");
                        uuid = session:getVariable("uuid");
			voicemail_id= "111";
                        voicemail_uuid = "63fb17d5-fd97-4feb-8b1f-c7daba9791e0";
                        caller_id_name = session:getVariable("caller_id_name");
                        caller_id_number = session:getVariable("caller_id_number");
                        effective_caller_id_number = session:getVariable("effective_caller_id_number");
                        effective_caller_id_name = session:getVariable("effective_caller_id_name");
                        voicemail_greeting_number = session:getVariable("voicemail_greeting_number");
			message_max_length = 30;
			message_silence_threshold = 400;
			message_silence_seconds = 3;

                --modify caller_id_number if effective_caller_id_number is set
                        if (effective_caller_id_number ~= nil) then
                                caller_id_number = effective_caller_id_number;
                        end
                --modify caller_id_name if effective_caller_id_name is set
                        if (effective_caller_id_name ~= nil) then
                                caller_id_name = effective_caller_id_name;
                        end

                --set default values
                        if (string.sub(caller_id_number, 1, 1) == "/") then
                                caller_id_number = string.sub(caller_id_number, 2, -1);
                        end
                        if (not record_silence_threshold) then
                                record_silence_threshold = 300;
                        end
                        if (not vm_message_ext) then
                                vm_message_ext = 'wav';
                        end

                --get the domain_uuid
                        domain_uuid = "a5f62ae9-32ce-47e8-bda6-2ff04cfafdaa";
                        domain_uuid = string.lower(domain_uuid);

                --set the voicemail_dir
                        voicemail_dir = "/var/lib/freeswitch/storage/voicemail/default/"..domain_name;
                        freeswitch.consoleLog("notice", "[voicemail] voicemail_dir: " .. voicemail_dir .. "\n");

		--play the instructions
			session:execute("playback", "/var/lib/freeswitch/recordings/ygwb.corpit.xyz/recording155.wav");

                --play the beep
                        dtmf_digits = '';
                        session:execute("playback","silence_stream://200");
                        session:streamFile("tone_stream://L=1;%(1000, 0, 640)");

                --start epoch
                        start_epoch = os.time();
		--begin recording
			result = session:recordFile(voicemail_dir.."/"..voicemail_id.."/msg_"..uuid..".wav", message_max_length, message_silence_threshold, message_silence_seconds);
		 --stop epoch
                        stop_epoch = os.time();

                --calculate the message length
                        message_length = stop_epoch - start_epoch;
                        message_length_formatted = format_seconds(message_length);
			session:setVariable("voicemail_message_seconds", message_length);
			voicemail_message_uuid = uuid;


		--store the message
								caller_id_name = string.gsub(caller_id_name,"'","''");
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
                                                                        voicemail_message_uuid = voicemail_message_uuid;
                                                                        domain_uuid = domain_uuid;
                                                                        voicemail_uuid = voicemail_uuid;
                                                                        start_epoch = start_epoch;
                                                                        caller_id_name = caller_id_name;
                                                                        caller_id_number = caller_id_number;
                                                                        message_base64 = message_base64;
                                                                        transcription = transcription;
                                                                        message_length = message_length;
                                                                };
								dbh:query(sql, params);
								dbh:release();

		end

--Playback call transfer
session:execute("playback", "${sound_prefix}/ivr/ivr-call_being_transferred.wav");

-- freeswitch.consoleLog("info", "Calling");

call = argv[1];
wait = argv[2];
code = argv[3];
context = argv[4];
legA = session:getVariable("uuid");

cmd = "uuid_hold " .. legA;
freeswitch.consoleLog("info", "[mwb_credit_card] ".. cmd .. "\n");

result = trim(api:executeString(cmd));

legB = freeswitch.Session("{ignore_early_media=true,originate_timeout=90,hangup_after_bridge=true,leg=1,}loopback/" .. call .. "/" .. context);

if (legB:ready()) then

	legB:sleep(wait);

	legB:execute("send_dtmf", code .. "@400");

	legB:sleep(wait);

	cmd = "uuid_hold off " .. legA;
	reuslt = trim(api:executeString(cmd));
	freeswitch.bridge(session, legB);

end

