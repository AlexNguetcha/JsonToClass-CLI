<?php

/**
 * JsonToClass allow you to create a php model
 * class from a json file.
 * 
 * @author Alex Nguetcha <nguetchaalex@gmail.com>
 */
class JsonToClass
{

    private $jsonData;
    private $attrs = [];
    private $class_name;
    private $str;
    private $attrsNames = [];
    private $isFluentSetter = true;

    public function __construct(string $filename)
    {
        $this->jsonData = file_get_contents($filename);
        $this->jsonData = json_decode($this->jsonData);
        $this->parse();
    }

    public function parse()
    {
        foreach ($this->jsonData as $key => $value) {
            if ($key === "class_name") {
                //get the class name
                $this->class_name = $value;
                $this->class_name = strtoupper($this->class_name[0]) . str_replace($this->class_name[0], "", $this->class_name);
            } else if ($key === "class_attrs") {
                //get all class attributes

                foreach ($value as $key => $val) {
                    //add attr name = ($key)
                    $attr = [];
                    array_push($attr, $key);
                    array_push($this->attrsNames, $key);
                    foreach ($val as $key => $v) {
                        //add attr visibility and type
                        array_push($attr, $v);
                    }
                    array_push($this->attrs, $attr);
                }
            }
        }
    }

    public function getAttrs()
    {
        return $this->attrs;
    }

    private function buildConstructor()
    {
        $constructorBody = "";
        $constructor = "\tpublic function __construct(";
        for ($i = 0; $i < count($this->attrsNames); $i++) {
            //add all constructor params
            $constructor .= $this->attrs[$i][1] . " $" . $this->attrsNames[$i];
            if ($i === count($this->attrsNames) - 1) {
                //close constructor params declaration
                $constructor .= ")\n\t{\n";
            } else {
                $constructor .= ", ";
            }
            //body declaration
            $constructorBody .= "\t\t" . "$" . "this->" . $this->attrsNames[$i] . " = " . "$" . $this->attrsNames[$i] . ";\n";
        }
        //add body declaration
        $constructor .= $constructorBody;
        $constructor .= "\t}\n";
        return $constructor;
    }

    private function buildGetters()
    {
        $getters = "";
        for ($i = 0; $i < count($this->attrsNames); $i++) {
            $attrName =  $this->attrsNames[$i];
            $type = $this->attrs[$i][1];
            $getterName = strtoupper($attrName[0]) . str_replace($attrName[0], "", $attrName);
            $func = "\tpublic function ";
            if ($type === "bool") {
                //isser name
                $func .= "is";
            } else {
                //getter name
                $func .= "get";
            }
            if ($type !== "mixed") {
                //add function return type
                $func .= $this->methodName($getterName) . "(): " . $type;
            }
            $func .= "\n\t{\n";

            $func .= "\t\treturn $" . "this->" . $attrName . ";\n";
            $func .= "\t}";
            $getters .= $func . "\n\n";
        }
        return $getters;
    }

    private function methodName($attrName): string
    {
        //check underscrore in the variable name
        $find = strpos($attrName, "_");
        if ($find !== false) {
            //example: create_at => createAt
            $attrName = \str_replace("_" . $attrName[$find + 1], strtoupper($attrName[$find + 1]), $attrName);
        }
        return $attrName;
    }

    private function buildSetters()
    {
        $setters = "";
        for ($i = 0; $i < count($this->attrsNames); $i++) {
            $attrName =  $this->attrsNames[$i];
            $type = $this->attrs[$i][1];
            $param = "$" . $attrName;
            $setterName = strtoupper($attrName[0]) . str_replace($attrName[0], "", $attrName);

            if ($type !== "mixed") {
                $param = $type . " " . $param;
            }
            $func = "\tpublic function set" . $this->methodName($setterName) . "(" . $param . ")";
            if ($this->isFluentSetter) {
                $func .= ": self";
            }
            $func .= "\n\t{\n";
            $func .= "\t\t$" . "this->" . $attrName . " = $" . $attrName . ";\n";
            if ($this->isFluentSetter) {
                $func .= "\t\treturn $" . "this;\n";
            }
            $func .= "\t}";
            $setters .= $func . "\n\n";
        }
        return $setters;
    }

    public function build(): string
    {
        //class declaration

        $this->str = "<?php\n\n";
        $this->str .= "class " . $this->class_name . "\n{\n";
        //attribute declaration
        for ($i = 0; $i < count($this->attrs); $i++) {
            $attr = $this->attrs[$i];
            $this->str .= "\t" . $attr[2] . " $" . $attr[0] . ";\n";
        }
        $this->str .= "\n" . $this->buildConstructor();
        $this->str .= "\n";
        $this->str .= $this->buildGetters();
        $this->str .= "\n";
        $this->str .= $this->buildSetters();
        $this->str .= "}";
        //print_r($this->str);
        return $this->str;
    }

    public function toFile(?string $filename = null, bool $force = false, bool $fluent = true)
    {
        $this->isFluentSetter = $fluent;
        if ($filename === null) {
            $filename = strtoupper($this->class_name[0]) . str_replace($this->class_name[0], "", $this->class_name);
            $filename .= ".php";
        }

        if (file_exists($filename) === true) {
            if ($force === false) {
                throw new Exception($filename . " already exist!");
            }
        }
        $file = fopen($filename, "w+");
        fwrite($file, $this->build());
        fclose($file);
    }
}

/**
 * JsonToClass cli interface
 * 
 * @author Alex Nguetcha <nguetchaalex@gmail.com>
 */
class CMD
{
    private $jsonTab = [];
    private $allowedAttributes = ["int", "string", "bool", "array", "DateTime"];
    private $allowedVisibility = ["public", "private"];

    public function error($message)
    {
        return
            "$message\n
              jtc [command] [argument]\n
             ";
    }
    public function m(string $message)
    {
        echo $message;
    }
    public function addAttribute()
    {
        $attributeName = readline("Enter attribute name (example:name) :"."\n");
        if ( !$attributeName ) {
            //end of adding attribute
            return false;
        } else {
            $this->m($attributeName."\n");
            do {
                $attributeType = readline("Enter attribute type (default [string]) :"."\n");
                if ( !$attributeType ) {
                    //set default attribute type
                    $attributeType = "string";
                }
                //check if attributeType is correct
                if (!in_array($attributeType, $this->allowedAttributes)) {
                    $this->m("attribute type error ".$attributeType."\n");
                }
            } while (!in_array($attributeType, $this->allowedAttributes));
            $this->m($attributeType."\n");
            do {
                $attributeVisibility = readline("Enter attribute visibility (default [private]) :"."\n");
                if ( !$attributeVisibility ) {
                    //set default attribute visibility
                    $attributeVisibility = "private";
                }
                //check if attributeVisibility is correct
                if (!in_array($attributeVisibility, $this->allowedVisibility)) {
                    $this->m("attribute visibility error " . $this->allowedVisibility);
                }
            } while (!in_array($attributeVisibility, $this->allowedVisibility));
            $this->m($attributeVisibility."\n");

            $this->jsonTab["class_attrs"][$attributeName] = [
                "type"=>$attributeType,
                "visibility"=>$attributeVisibility
            ];
            return true;
        }
    }

    public function execute($args)
    {
        if (count($args) === 3) {
            $command = $args[1];
            $classname = $args[2];
            if ($command === "make") {
                //generate tmp json filename
                $tmpJsonFileName = date("YmdHis") . ".json";
                //add class name 
                $this->jsonTab["class_name"] = $classname;
                $continue = true;
                //add all class attribute
                do {
                    $continue = $this->addAttribute();
                } while ($continue === true);
                //generate json file 
                $this->m("[aborted!]\n");
                $this->m("generate json file...\n");
                $file = fopen($tmpJsonFileName, "w+");
                fwrite($file, json_encode($this->jsonTab));
                fclose($file);
                //parse json file to php
                $this->m("parse json file to php...\n");
                $jtc = new JsonToClass($tmpJsonFileName);
                $jtc->toFile();
                $this->m("php class generated !!\n\n");
                //delete generate json file
                unlink($tmpJsonFileName);
                exit();
            } else {
                return $this->error($command . " not found");
            }
        } else {
            return $this->error("jtc error :");
        }
    }
}

$cli = new CMD();
echo $cli->execute($argv);
