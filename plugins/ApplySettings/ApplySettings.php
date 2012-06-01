<?php
namespace ApplySettings;

use \Mage as Mage;
use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\PluginInterface as JumpstormPlugin;

/**
 * apply some settings
 */
class ApplySettings implements JumpstormPlugin
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function execute()
    {
        $settings = $this->config->plugins->ApplySettings;
        if ($settings instanceof \Zend_Config) {
            foreach ($this->config->plugins->ApplySettings as $name=>$setting) {
                if ($setting instanceof \Zend_Config && $setting->path && isset($setting->value)) {
                    Mage::getModel('eav/entity_setup', 'core_setup')->setConfigData(
                        $setting->path,
                        $setting->value
                    );
                    Logger::log('* Applied setting %s', array($name));
                } else {
                    Logger::error('Did not apply setting %s', array($name), false);
                }
            }
        } else {
            Logger::error('Invalid configuration for plugin ApplySettings', array(), false);
        }
    }
}


