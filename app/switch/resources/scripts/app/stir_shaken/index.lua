-- Set call attestation for outbound calls

-- See if the outbound caller_id is in the destinations table, if so set to A

	outbound_caller_id_number = string.sub(session:getVariable("outbound_caller_id_number", -10));

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
		sql = sql .. "WHERE destination_number LIKE %:destination_number ";
        sql = sql .. "AND destination_type = 'inbound' ";
		sql = sql .. "AND destination_enabled = true ";
		local params = {destination_number = outbound_caller_id_number};
		if (debug["sql"]) then
			freeswitch.consoleLog("notice", "SQL:" .. sql .. "; destination_number: " .. destination_number .. "\n");
		end
		dbh:query(sql, params, function(row)

        --set the cache
            if (row.count > 0) then
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

    -- We're not in the destination table, maybe it's a call forward, check and resign
    -- ATIS-1000085 says this method is wrong by as of 2-2026 most libraries don't support the spec
    
    -- We're willing to sign if it's up to 10 minutes, in case the call is transferred
    session:setVariable("sip_stir_shaken_vs_max_age", "600")

    session:execute("sofia_stir_shaken_vs", "")
    verstat_data = session:getVariable("sip_verstat_detailed")
    attest = string.match(verstat_data, 'TN-Validation-Passed-([ABC])');
    
    if attest ~= nil then
        session:execute("export", "sip_stir_shaken_attest=" .. attest);
        return
    end

    --If we're here, you either don't have a header, or you're forged.
    session:execute("export", "sip_stir_shaken_attest=B");
