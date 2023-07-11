--BEGIN TRANSACTION;
CREATE INDEX index_billsec ON v_xml_cdr(billsec);
CREATE INDEX index_caller_id_name ON v_xml_cdr(caller_id_name);
CREATE INDEX index_destination_number ON v_xml_cdr(destination_number);
CREATE INDEX index_duration ON v_xml_cdr(duration);
CREATE INDEX index_hangup_cause ON v_xml_cdr(hangup_cause);
CREATE INDEX index_start_stamp ON v_xml_cdr(start_stamp);
CREATE INDEX callcenteragent on v_xml_cdr USING HASH ((json->'variables'->>'cc_agent'));
CREATE INDEX callcenterqueueanswered on v_xml_cdr USING HASH ((json->'variables'->>'cc_cause'));
CREATE INDEX callcenterqueueuuid on v_xml_cdr USING HASH ((json->'variables'->>'call_center_queue_uuid')); 
--COMMIT;
