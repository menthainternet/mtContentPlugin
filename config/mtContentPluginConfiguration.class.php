<?php

/**
 * mtContentPlugin configuration.
 * 
 * @package mtContentPlugin
 * @author  szinya <szinya@mentha.hu>
 */
class mtContentPluginConfiguration extends sfPluginConfiguration
{
 /**
  * @see sfPluginConfiguration
  */
  public function initialize()
  {
    if (in_array('mtContent', sfConfig::get('sf_enabled_modules', array())))
    {
      $this->dispatcher->connect('routing.load_configuration', array('mtContentRouting', 'listenToRoutingLoadConfigurationEvent'));
    }
  }
}
