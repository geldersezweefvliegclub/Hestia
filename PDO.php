<?php

if (!IsSet($GLOBALS['DATABASE_INCLUDED']))
{
	$GLOBALS['DATABASE_INCLUDED'] = 1;
		
	class DB
	{
		var $conn = null;
		var $data_retrieved;
		var $rows;
		var $row;
		
		function ClearCache()
		{
			$data_retrieved = null;
			$rows = null;
			$row = null;
		}

		function Connect($conn = '')
		{
			global $db_info;

			if ($conn == '')
			{
				if (!IsSet($GLOBALS['__DB_CONN__']))
				{
					$dsn = sprintf("%s:host=%s;dbname=%s;charset=utf8", $db_info['dbType'],
																		$db_info['dbHost'],
																		$db_info['dbName']);
					$this->conn = new PDO(
						$dsn, $db_info['dbUser'], $db_info['dbPassword'], 
						array(PDO::MYSQL_ATTR_FOUND_ROWS => true));
					$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					
					$GLOBALS['__DB_CONN__'] = $this->conn;
				}
				else
					$this->conn = $GLOBALS['__DB_CONN__'];
			}
			else
				$this->conn = $conn;

			return $this->conn;
		}
		
		function DbOpvraag($query, $params = null)
		{
			global $app_settings;

			Debug(__FILE__, __LINE__, sprintf("DbOpvraag(%s, %s)", $query, json_encode($params)));

			$this->Record = array();

			if (!$this->conn)
				$this->Connect();
			
			if ($this->conn)
			{
				$sth = $this->conn->prepare($query);
				try
				{
					if ($params == null)
						$sth->execute();
					else
					{
						$sth->execute($params);
					}
					
					if ($app_settings['DbLogging'])
					{
						ob_start(); 
						$sth->debugDumpParams();
						$q = ob_get_clean();

						if ($app_settings['LogDir'] == "syslog")
							error_log($q);
						else
							error_log($q  . "\n", 3, $app_settings['LogDir'] . "sql.txt");								
					}					
	
					$this->data_retrieved = $sth->fetchAll(PDO::FETCH_ASSOC);
					$this->rows = $sth->rowCount();
					$this->row = -1;
				}
				catch (PDOException $e) 
				{
					HestiaError(__FILE__, __LINE__, $query);
					HestiaError(__FILE__, __LINE__,  $e->getMessage());
					die;
				}
			}
		}
	
		
		function DbUitvoeren($query)
		{
			global $app_settings;
			$rowcount = -1;

			Debug(__FILE__, __LINE__, sprintf("DbUitvoeren(%s)", $query));
			
			if (!$this->conn)
				$this->Connect();
				
			if ($this->conn)
			{
				if ($app_settings['DbLogging'])
				{
					if ($app_settings['LogDir'] == "syslog")
					{
						error_log($query);
					}
					else
					{
						error_log($query  . "\n", 3, $app_settings['LogDir'] . "exec.txt");
					}
				}
				
				try
				{
					$rowcount = $this->conn->exec($query);		// return number of rows affected
				}
				catch (PDOException $e) 
				{
					HestiaError(__FILE__, __LINE__, $query);
					HestiaError(__FILE__, __LINE__,  $e->getMessage());
					die;
				}
				$this->ClearCache();
				return $rowcount;
			}
			else
				return -1;
		}
		
		function Data()
		{
			return $this->data_retrieved;
		}
	
		function DbToevoegen($table, $array)
		{
			global $app_settings;

			Debug(__FILE__, __LINE__, sprintf("DbToevoegen(%s, %s)", $table, json_encode($array)));
			
			$fields = "";
			$values = "";

			foreach ($array as $field => $value)
			{
				$field = str_replace("'","''", $field);
				
				if ($fields == "")
					$fields = sprintf("%s", $field);
				else
					$fields = sprintf("%s,%s", $fields, $field);
					
				if ($values == "")
				{
					if ($value === null)
						$values = sprintf("NULL");
					elseif ((is_numeric($value)) && (substr($value,0,1) != "0"))
						$values = sprintf("%s", $value);
					else
						$values = sprintf("'%s'", $value);
				}
				else
				{
					if ($value === null)
						$values = sprintf("%s,  NULL", $values);
					elseif ((is_numeric($value)) && (substr($value,0,1) != "0")) 
						$values = sprintf("%s,%s", $values, $value);
					else
						$values = sprintf("%s,'%s'", $values, str_replace("'","\'", $value));
				}
			}
			$query = sprintf("INSERT INTO %s (%s) VALUES (%s);", $table, $fields, $values);

			if ($app_settings['DbLogging'])
			{
				if ($app_settings['LogDir'] == "syslog")
				{
					error_log($query);
				}
				else
				{				
					error_log($query  . "\n", 3, $app_settings['LogDir'] . "insert.txt");
				}
			}


			if (!$this->conn)
				$this->Connect();
				
			if ($this->conn)
			{
				try
				{
					$sth = $this->conn->prepare($query);
					$sth->execute();
				}
				catch (PDOException $e) 
				{
					HestiaError(__FILE__, __LINE__, $query);
					HestiaError(__FILE__, __LINE__,  $e->getMessage());
					die;
				}
				
				$lastid = $this->conn->lastInsertId();		// return inserted ID
				
				if ($app_settings['DbLogging'])
				{
					if ($app_settings['LogDir'] == "syslog")
					{
						error_log("ID=" . $lastid);
					}
					else
					{
						error_log("ID=" . $lastid  . "\n", 3, $app_settings['LogDir'] . "insert.txt");
					}
				}
								
				$this->ClearCache();
				return $lastid;
			}
		}

		function DbAanpassen($table, $ID, $array)
		{
			global $app_settings;
			Debug(__FILE__, __LINE__, sprintf("DbAanpassen(%s, %s, %s)", $table, $ID, json_encode($array)));
		
			$fields = "";
			$retval = false;
			
			foreach ($array as $field => $value)
			{
				$field = str_replace("'","''", $field);
			
				if ($fields == "")
				{
					if ($value === null)
						$fields = sprintf("%s=NULL", $field);
					elseif ((is_numeric($value)) && (substr($value,0,1) != "0")) 
						$fields = sprintf("%s=%s", $field, $value);
					else
						$fields = sprintf("%s='%s'", $field, str_replace("'","\'", $value));
				}
				else
				{
					error_log($field . "=" . $value  . "\n", 3, $app_settings['LogDir'] . "update.txt");

					if ((is_numeric($value)) && (substr($value,0,1) != "0")) 
							$fields = sprintf("%s,%s=%s", $fields, $field, $value);
					elseif ($value === null)
						$fields = sprintf("%s,%s=NULL", $fields, $field);
					else
						$fields = sprintf("%s,%s='%s'", $fields, $field, str_replace("'","\'", $value));
				}
			}
			if (is_numeric($ID))
				$query = sprintf("UPDATE %s SET %s WHERE ID=%s;", $table, $fields, $ID);
			else
				$query = sprintf("UPDATE %s SET %s WHERE ID='%s';", $table, $fields, $ID);
			
			if ($app_settings['DbLogging'])
			{
				if ($app_settings['LogDir'] == "syslog")
				{
					error_log($query);
				}
				else
				{
					error_log($query  . "\n", 3, $app_settings['LogDir'] . "update.txt");
				}
			}

			if (!$this->conn)
				$this->Connect();
				
			if ($this->conn)
			{
				try
				{
					$sth = $this->conn->prepare($query);
					$retval = $sth->execute();
					$this->rows = $sth->rowCount();
				}
				catch (PDOException $e) 
				{
					HestiaError(__FILE__, __LINE__, $query);
					HestiaError(__FILE__, __LINE__,  $e->getMessage());
					die;
				}
				return $retval;
			}
		}

		public function Rows()
		{
			return $this->rows;
		}
	}
}
?>
