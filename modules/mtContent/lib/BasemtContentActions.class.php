<?php

/**
 * Base mtContent actions.
 *
 * @package mtContentPlugin
 * @author  szinya <szinya@mentha.hu>
 */
class BasemtContentActions extends sfActions
{
 /**
  * Proxy action to set filename on client side.
  *
  * @param sfRequest $request A request object
  */
  public function executeSetFilename(sfWebRequest $request)
  {
    $userAttributes = $this->getUser()->getAttributeHolder();

    // if redirect not initialized
    $this->forward404Unless(false === $userAttributes->get('redirected', null, mtContent::NS_SET_FILENAME));

    // set redirect status
    $userAttributes->set('redirected', true, mtContent::NS_SET_FILENAME);

    // restore request method and params
    $requestParams = $request->getParameterHolder();
    $requestParams->clear();

    $request->setMethod($userAttributes->get('request_method', null, mtContent::NS_SET_FILENAME));
    $requestParams->add($userAttributes->get('request_params', null, mtContent::NS_SET_FILENAME));

    // forward to caller action
    $this->forward($request->getParameter('module'), $request->getParameter('action'));
  }
}
