<?php
require('Services/IpCheck.php');
Services\IpCheck::isIpValidOrDie($_SERVER['REMOTE_ADDR']);

    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: text/html; charset=utf-8');

    // ================================================
    
    require('StApp.php');

    // ================================================
    
    $stApp = stApp::instance();
    $table = $argv[1] ?? '';
	
    if (!empty($table)) {
        $tableCamelCase = $stApp->comTools->camelCase($table);
        $tableExtractor = $stApp->getContainer(\Services\EntityManager\TableInfo::class);
        $description = $tableExtractor->extract($table);
        
        if ($description !== false) {
            $output = "";
            $output .= "<?php\n";
            $output .= "    namespace Services\Entity;\n";
            $output .= "\n";
            $output .= "    /**\n";
            $output .= "     * Class {$tableCamelCase}Entity\n";
            $output .= "     * @package Services\Entity\n";
            $output .= "     * @Orm\Table(name=\"{$table}\")\n";
            $output .= "     */\n";
            $output .= "    class {$tableCamelCase}Entity {\n";
            $output .= "";
            
            // member variables
            foreach($description as $desc) {
                $columnCamelCase =  lcfirst($stApp->comTools->camelCase($desc["name"]));
				
				// add ? for nullable
				if ($desc["nullable"]) {
					$acceptType = "?{$desc["php_type"]}";
				} else {
					$acceptType = "{$desc["php_type"]}";
				}
				
				$output .= "        /**\n";
                $output .= "         * @Orm\Column(name=\"{$desc["name"]}\", type=\"{$desc["type"]}\"";
                
                if (!empty($desc["length"])) {
                    if (is_numeric($desc["length"])) {
                        $output .= ", length={$desc["length"]}";
                    } else {
                        $output .= ", length=\"{$desc["length"]}\"";
                    }
                }

                if ($desc["nullable"]) {
                    $output .= ", nullable=true";
                }

                if ($desc["primary_key"]) {
                    $output .= ", primary_key=true";
                }

                if ($desc["auto_increment"]) {
                    $output .= ", auto_increment=true";
                }

                if (!empty($desc["default"])) {
                    $output .= ", default=\"{$desc["default"]}\"";
                }
                
                $output .= ")\n";
                $output .= "         */\n";
                $output .= "        private {$acceptType} \${$columnCamelCase}";
				
				if (!empty($desc["default"])) {
					if (is_numeric($desc["default"])) {
						$output .= " = {$desc["default"]}";
					} else {
						$output .= " = \"{$desc["default"]}\"";
					}
				}
				
				$output .= ";\n\n";
            }
            
            // setters & getters
            foreach($description as $desc) {
                $fieldCamelCase = $stApp->comTools->camelCase($desc["name"]);
                $variableCamelCase = lcfirst($fieldCamelCase);
                
                // add ? for nullable
                if ($desc["nullable"]) {
                    $acceptType = "?{$desc["php_type"]}";
                } else {
                    $acceptType = "{$desc["php_type"]}";
                }
				
                $output .= "\n";
                
                if (!empty($acceptType)) {
                    $output .= "        public function get{$fieldCamelCase}() : {$acceptType} {\n";
                } else {
                    $output .= "        public function get{$fieldCamelCase}() {\n";
                }
    
                $output .= "            return \$this->{$variableCamelCase};\n";
                $output .= "        }\n";
                $output .= "\n";
    
                if (!$desc["primary_key"] || !$desc["auto_increment"]) {
                    $output .= "        public function set{$fieldCamelCase}({$acceptType} \$value): {$tableCamelCase}Entity {\n";
                    $output .= "            \$this->{$variableCamelCase} = \$value;\n";
                    $output .= "            return \$this;\n";
                    $output .= "        }\n";
                }
            }
    
            $output .= "    }\n";
            
            file_put_contents("Services/Entity/{$tableCamelCase}Entity.php", $output);
        }
    }
    