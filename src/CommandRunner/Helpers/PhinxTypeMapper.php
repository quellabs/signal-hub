<?php
	
	namespace Quellabs\ObjectQuel\CommandRunner\Helpers;
	
	
	class PhinxTypeMapper {
		
		/**
		 * Map entity data type to Phinx data type
		 * @param string $type Entity data type
		 * @return string Phinx data type
		 */
		public function mapToPhinxType(string $type): string {
			$map = [
				'int'        => 'integer',
				'integer'    => 'integer',
				'tinyint'    => 'boolean',
				'smallint'   => 'integer',
				'mediumint'  => 'integer',
				'bigint'     => 'biginteger',
				'float'      => 'float',
				'double'     => 'double',
				'decimal'    => 'decimal',
				'char'       => 'char',
				'varchar'    => 'string',
				'text'       => 'text',
				'mediumtext' => 'text',
				'longtext'   => 'text',
				'date'       => 'date',
				'datetime'   => 'datetime',
				'timestamp'  => 'timestamp',
				'time'       => 'time',
				'enum'       => 'enum'
			];
			
			return $map[strtolower($type)] ?? 'string';
		}

		/**
		 * Format a value for inclusion in PHP code
		 * @param mixed $value The value to format
		 * @return string Formatted value
		 */
		public function formatValue(mixed $value): string {
			if (is_null($value)) {
				return 'null';
			} elseif (is_bool($value)) {
				return $value ? 'true' : 'false';
			} elseif (is_int($value) || is_float($value)) {
				return (string)$value;
			} else {
				return "'" . addslashes($value) . "'";
			}
		}
	}