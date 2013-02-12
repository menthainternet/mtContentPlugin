<?php

/**
 * mtContentPlugin routing.
 *
 * @package mtContentPlugin
 * @author  szinya <szinya@mentha.hu>
 */
class mtContentRouting
{
 /**
  * Listens to the routing.load_configuration event.
  *
  * @static
  * @param sfEvent $event An sfEvent instance
  */
  static public function listenToRoutingLoadConfigurationEvent(sfEvent $event)
  {
    /** @var $r sfPatternRouting */
    $r = $event->getSubject();

    $r->prependRoute('mt_content_set_filename', new sfRoute('/:sf_culture/mtContent/setFilename/:mt_content_module/:mt_content_action', array(
      'module' => 'mtContent',
      'action' => 'setFilename'
    )));

    $r->prependRoute('mt_content_set_filename_fn', new sfRoute('/:sf_culture/mtContent/setFilename/:mt_content_module/:mt_content_action/:mt_content_filename', array(
      'module' => 'mtContent',
      'action' => 'setFilename'
    ), array(), array(
      'segment_separators' => array('/')
    )));
  }
}
