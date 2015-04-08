<?php
/**
 * Saving conversion logs.
 */
class ConversionLogger {
	
	protected $data;
	protected $logId;
	protected $db;

	public function __construct() {
		$this->data = array(
			'submit_time' => date('Y-m-d H:i:s'),
			'ip' => $_SERVER['REMOTE_ADDR'],
			'host' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
			'user_agent' => @$_SERVER['HTTP_USER_AGENT'],
		);
		
		$this->db = new PDO('sqlite:logs/logs.sqlite');
		$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	
	public function log(array $data) {
		if (!$this->logId) {
			// insert new log
			$data = array(
				'submit_time' => date('Y-m-d H:i:s'),
				'ip' => $_SERVER['REMOTE_ADDR'],
				'host' => gethostbyaddr($_SERVER['REMOTE_ADDR']),
				'user_agent' => @$_SERVER['HTTP_USER_AGENT'],
			) + $data;
			
			$fields = "";
			$placeholders = "";
			
			foreach ($data as $field => $val) {
				if ($fields !== "") {
					$fields .= ", ";
					$placeholders .= ", ";
				}
				
				$fields .= $field;
				$placeholders .= ":$field";
			}
			
			$sql = "INSERT INTO conversion_log ($fields)
				VALUES ($placeholders)";
			
			$stmt = $this->db->prepare($sql);
			
			foreach ($data as $field => $val) {
				$stmt->bindValue(":$field", $val);
			}
			
			$stmt->execute();
			$this->logId = $this->db->lastInsertId();
			
			
		} else {
			// add to existing log
			$set = "";
			
			foreach ($data as $field => $val) {
				if ($set !== "") {
					$set .= ", ";
				}
				
				$set .= "$field=:$field";
			}
			
			$sql = "UPDATE conversion_log SET $set WHERE id=$this->logId";
			
			$stmt = $this->db->prepare($sql);
			
			foreach ($data as $field => $val) {
				$stmt->bindValue(":$field", $val);
			}
			
			$stmt->execute();
		}
	}
}
