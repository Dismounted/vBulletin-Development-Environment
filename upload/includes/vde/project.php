<?php

class VDE_Project {
    public $id;
    public $active;
    public $encoding;
    public $buildPath;
    public $meta;
    public $files;
    
    protected $_path;
    
	protected $_items = array(
		'templates' => array(
			'path' => 'templates',
			'ext' => '.html'
		),
		'phrases' => array(
			'path' => 'phrases',
			'ext' => '.txt'
		),
		'options' => array(
			'path' => 'options',
			'ext' => '.php'
		),
		'plugins' => array(
			'path' => 'plugins',
			'ext' => '.php'
		),
		'scheduled_tasks' => array(
			'path' => 'cron',
			'ext' => '.php'
		),
		'codes' => array(
		  'path' => 'installcodes',
		  'ext'  => '.php'
		)
	);
    
    public function __construct($path) {
        if (!file_exists("$path/config.php")) {
            throw new VDE_Project_Exception("No project found at $path");
        }
    
        $this->_path = $path;
        require("$path/config.php");
        
        $this->id = $config['id'];
        $this->active = isset($config['active']) ? $config['active'] : 1;
        $this->encoding = $config['encoding'] ? $config['encoding'] : 'ISO-8859-1';
        
        $this->meta = array(
            'title'       => $config['title'],
            'description' => $config['description'],
            'url'         => $config['url'],
            'versionurl'  => $config['versionurl'],
            'version'     => $config['version'],
            'author'      => $config['author']
        );
        
        $this->buildPath = $config['buildpath'];        
        $this->files = $config['files'];
        $this->_dependencies = $config['dependencies'];
    }
    
    public function getDependencies() {
        return $this->_dependencies;
    }   
    
    public function getCodes() {
        if (!is_dir($dir = $this->_path . '/updown')) {   
            return array();   
        }
        
        $versions = array();
        foreach (scandir($dir) as $file) {
            $matches = null;
            if (preg_match('/^(up|down)-(.*)\.php$/', $file, $matches)) { 
                list($null, $updown, $version) = $matches;

                $versions[$version][$updown] = $this->_getEvalableCode(file_get_contents(
                    "$dir/$file"
                ));
           }
        }
        
        return $versions;
    }
    
    public function getTemplates() {
        $templates = array();
        
        foreach (scandir($dir = $this->_path . '/templates') as $file) {
            if (substr($file, -5) != '.html') {
                continue;
            }
            
            $templates[substr($file, 0, -5)] = file_get_contents("$dir/$file");
        }
        
        return $templates;
    }
    
    public function getExtendedTemplates() {
        $templates = array();
        
        if (!is_dir($dir = $this->_path . '/templates')) {
            return array();
        }
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -5) != '.html') {
                continue;
            }
            
            $templates[] = array(
                'name'     => substr($file, 0, -5),
                'template' => file_get_contents("$dir/$file"),
                'version'  => $this->meta['version'],
                'author'   => $this->meta['author']
            );
        }
        
        return $templates;
    }
    
    public function getPlugins() {
        $plugins = array();
        
        foreach (scandir($dir = $this->_path . '/plugins') as $file) {
            if (substr($file, -4) != '.php') {
                continue;
            }
            
            $plugins[substr($file, 0, -4)] = $this->_getEvalableCode(file_get_contents("$dir/$file"));
        }
        
        return $plugins;
    }
    
    public function getExtendedPlugins() {
        $plugins = array();
        
        if (!is_dir($dir = $this->_path . '/plugins')) {
            return array();   
        }
        
        foreach (scandir($dir) as $file) {
            if (substr($file, -4) != '.php') {
                continue;
            }
            
            //todo get title, active, executionorder from file header
            
            $plugins[] = array(
                'hookname'       => $hook = substr($file, 0, -4),
                'title'          => $this->meta['title'] . " - $hook",
                'active'         => 1,
                'executionorder' => 10,
                'code'           => $this->_getEvalableCode(file_get_contents("$dir/$file"))
            );
        }
        
        return $plugins;
    }
    
    protected function _getEvalableCode($code) {
        return trim(trim($code, '<?php'));
    }
    
    public function getOptions() {
        $options = array();
        
        if (!is_dir($dir = $this->_path . '/options')) {
            return array();   
        }
        
        foreach (scandir($dir) as $groupDirName) {
            if (file_exists($groupFile = "$dir/$groupDirName/$groupDirName.php")) {
                
                foreach (scandir("$dir/$groupDirName") as $optionFileName) {
                    $optionFile = "$dir/$groupDirName/$optionFileName";
                    if ($optionFile == $groupFile or substr($optionFile, -4) != '.php') {
                        continue;
                    }
                    
                    require($optionFile);
                    $options[substr($optionFileName, 0, -4)] = isset($option['value']) ? $option['value'] : $option['defaultvalue'];
                    
                }
            }            
        }
       
        return $options;   
    }
    
    public function getExtendedOptions() {
        $groups = array();
        
        if (!is_dir($dir = $this->_path . '/options')) {
            return array();   
        }
        
        foreach (scandir($dir) as $groupDirName) {
            if (file_exists($groupFile = "$dir/$groupDirName/$groupDirName.php")) {
                $group = array();
                require($groupFile);
                $group['varname'] = $groupDirName;
                $group['options'] = array();
                
                foreach (scandir("$dir/$groupDirName") as $optionFileName) {
                    $optionFile = "$dir/$groupDirName/$optionFileName";
                    if ($optionFile == $groupFile or substr($optionFile, -4) != '.php') {
                        continue;
                    }
                    
                    require($optionFile);
                    $option['varname'] = substr($optionFileName, 0, -4);
                    $group['options'][] = $option;
                }
                
                $groups[] = $group;
            }            
        }
       
        return $groups;
    }
    
    public function getPhrases() {
        $phrases = array();
        
        if (!is_dir($dir = $this->_path . '/phrases')) {
            return array();
        }
        
        foreach (scandir($dir) as $sub) {
            if (!preg_match('/^([a-z0-9]+)$/i', $sub)) {
                continue;
            }
            
            foreach (scandir("$dir/$sub") as $phrasefile) {
                if (substr($phrasefile, -4) != '.txt') {
                    continue;
                }
                
                $varname = substr($phrasefile, 0, -4);
                $phrases[$sub][$varname] = file_get_contents("$dir/$sub/$phrasefile");
            }
        }
        
        return $phrases;
    }
    
    public function getExtendedPhrases() {
        $phraseTypes = array();
        
        if (!is_dir($dir = $this->_path . '/phrases')) {
            return array();
        }
        
        foreach (scandir($dir) as $fieldName) {
            if (file_exists($fieldFile = "$dir/$fieldName/$fieldName.txt")) {
                $phraseType = array(
                    'title'     => trim(file_get_contents($fieldFile)),
                    'fieldname' => $fieldName,
                    'phrases'   => array()
                );
                
                foreach (scandir("$dir/$fieldName") as $varname) {
                    $phraseFile = "$dir/$fieldName/$varname";
                    if ($phraseFile == $fieldFile or substr($phraseFile, -4) != '.txt') {
                        continue;
                    }
                    
                    $phraseType['phrases'][substr($varname, 0, -4)] = array(
                        'varname' => substr($varname, 0, -4),
                        'text'    => trim(file_get_contents($phraseFile))
                    );
        
                }
                
                $phraseTypes[$fieldName] = $phraseType;
            }            
        }
       
        return $phraseTypes;
    }
}



class VDE_Project_Exception extends Exception {

}