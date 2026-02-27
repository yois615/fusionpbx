-- Set call attestation for outbound calls

-- See if the outbound caller_id is in the destinations table, if so set to A

	outbound_caller_id_number = string.sub(session:getVariable("outbound_caller_id_number"), -10);

--includes
	local cache = require "resources.functions.cache"

--include json library
	local json
	if (debug["sql"]) then
		json = require "resources.functions.lunajson"
	end

--prepare the api object
	api = freeswitch.API();

--define the trim function
	require "resources.functions.trim";

--set the cache key
	key = "app:dialplan:outbound:stir_shaken:" .. outbound_caller_id_number;

--get the destination number
	value, err = cache.get(key);
	if (err == 'NOT FOUND') then

		--connect to the database
		local Database = require "resources.functions.database";
		local dbh = Database.new('system');

		--select data from the database
		local sql = "SELECT COUNT(destination_number) ";
		sql = sql .. "FROM v_destinations ";
		sql = sql .. "WHERE destination_number LIKE :destination_number ";
        sql = sql .. "AND destination_type = 'inbound' ";
		sql = sql .. "AND destination_enabled = 'true' ";
		local params = {destination_number = "%" .. outbound_caller_id_number};
		if (debug["sql"]) then
			freeswitch.consoleLog("notice", "SQL:" .. sql .. "; destination_number: " .. destination_number .. "\n");
		end
		dbh:query(sql, params, function(row)

        --set the cache
            if (tonumber(row.count) > 0) then
                value = "true"
                ok, err = cache.set(key, value, 86400);
            else
                value = "false";
                ok, err = cache.set(key, value, 600);
            end
        end);
    end

    if value == "true" then
        session:execute("export", "sip_stir_shaken_attest=A");
        return;
    end

    -- We're not in the destination table, maybe it's a call forward
    -- See how we verified earlier, we wiped this header on the way in so we're not blindly trusting anyone.
    -- ATIS-1000085.v003 says this method is wrong but as of 3-2026 no OSS libraries support the spec
    
    attest = session:getVariable("sip_h_P-Attestation-Indicator");
    session:execute("export", "sip_h_Diversion=")
    
    if attest ~= nil then
        session:execute("export", "sip_stir_shaken_attest=" .. attest);
        return
    end

    --If we're here, you either don't have a header, or you're forged.
    session:execute("export", "sip_stir_shaken_attest=C");