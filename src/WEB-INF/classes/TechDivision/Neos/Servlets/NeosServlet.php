<?php

/**
 * TechDivision\Neos\Servlets\NeosServlet
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 */

namespace TechDivision\Neos\Servlets;

use TechDivision\ServletContainer\Interfaces\Request;
use TechDivision\ServletContainer\Interfaces\Response;
use TechDivision\ServletContainer\Servlets\PhpServlet;
use TechDivision\ServletContainer\Http\PostRequest;

/**
 * @package     TechDivision\Neos
 * @copyright   Copyright (c) 2013 <info@techdivision.com> - TechDivision GmbH
 * @license     http://opensource.org/licenses/osl-3.0.php
 *              Open Software License (OSL 3.0)
 * @author      Johann Zelger <jz@techdivision.com>
 */
class NeosServlet extends PhpServlet
{

    public function getWebappPath() {
        return $this->getServletConfig()->getApplication()->getWebappPath();
    }
    
    /**
     * Initialize global files
     *
     * @return void
     */
    public function initFiles()
    {
        // init query parser
        $this->getQueryParser()->clear();
        // iterate all files
    
        foreach ($this->getRequest()->getParts() as $part) {
            // check if filename is given, write and register it
            if ($part->getFilename()) {
                // generate temp filename
                $tempName = tempnam(ini_get('upload_tmp_dir'), 'neos_upload_');
                // write part
                $part->write($tempName);
                // register uploaded file
                appserver_register_file_upload($tempName);
                // init error state
                $errorState = UPLOAD_ERR_OK;
            } else {
                // set error state
                $errorState = UPLOAD_ERR_NO_FILE;
                // clear tmp file
                $tempName = '';
            }
            // check if file has array info
            if (preg_match('/^([^\[]+)(\[.+)?/', $part->getName(), $matches)) {
    
                // get first part group name and array definition if exists
                $partGroup = $matches[1];
                $partArrayDefinition = '';
                if (isset($matches[2])) {
                    $partArrayDefinition = $matches[2];
                }
    
                $this->getQueryParser()->parseKeyValue(
                    $partGroup.'[name]'.$partArrayDefinition, $part->getFilename()
                );
                $this->getQueryParser()->parseKeyValue(
                    $partGroup.'[type]'.$partArrayDefinition, $part->getContentType()
                );
                $this->getQueryParser()->parseKeyValue(
                    $partGroup.'[tmp_name]'.$partArrayDefinition, $tempName
                );
                $this->getQueryParser()->parseKeyValue(
                    $partGroup.'[error]'.$partArrayDefinition, $errorState
                );
                $this->getQueryParser()->parseKeyValue(
                    $partGroup.'[size]'.$partArrayDefinition, $part->getSize()
                );
            }
        }
        // set files globals finally.
        $_FILES = $this->getQueryParser()->getResult();
    }

    /**
     * Initialize globals
     *
     * @return void
     */
    public function initGlobals()
    {
        
        if (($xRequestedWith = $this->getRequest()->getHeader('X-Requested-With')) != null) {
            $this->getRequest()->setServerVar('HTTP_X_REQUESTED_WITH', $xRequestedWith);
        }

        $this->getRequest()->setServerVar(
            'DOCUMENT_ROOT', $this->getRequest()->getServerVar('DOCUMENT_ROOT') . DIRECTORY_SEPARATOR . 'Web'
        );
        
        $this->getRequest()->setServerVar(
            'SCRIPT_FILENAME', $this->getRequest()->getServerVar('DOCUMENT_ROOT') . DIRECTORY_SEPARATOR . 'index.php'
        );
        
        $this->getRequest()->setServerVar(
            'REQUEST_URI', str_replace('/index.php', '', $this->getRequest()->getServerVar('REQUEST_URI'))
        );
        
        $this->getRequest()->setServerVar('SCRIPT_NAME', DIRECTORY_SEPARATOR . 'index.php');
        $this->getRequest()->setServerVar('PHP_SELF', DIRECTORY_SEPARATOR . 'index.php');

        $_SERVER = $this->getRequest()->getServerVars();
        $_SERVER['SERVER_PORT'] = NULL;

        // check post type and set params to globals
        if ($this->getRequest() instanceof PostRequest) {
            $_POST = $this->getRequest()->getParameterMap();
            // check if there are get params send via uri
            parse_str($this->getRequest()->getQueryString(), $_GET);
        } else {
            $_GET = $this->getRequest()->getParameterMap();
        }

        $_REQUEST = $this->getRequest()->getParameterMap();
        
        foreach (explode('; ', $this->getRequest()->getHeader('Cookie')) as $cookieLine) {

            list ($key, $value) = explode('=', $cookieLine);

            if (empty($key) === false) {
                $_COOKIE[$key] = $value;
            }
        }
    }

    /**
     * @param Request $req
     * @param Response $res
     * @throws \Exception
     */
    public function doGet(Request $req, Response $res) {
        // register request and response objects
        $this->setRequest($req);
        $this->setResponse($res);

        // start session
        $this->getRequest()->getSession()->start();

        require($this->getWebappPath() . '/Packages/Framework/TYPO3.Flow/Classes/TYPO3/Flow/Core/Bootstrap.php');

        // initialize the global variables
        $this->initGlobals();
        
        // initialize the global files var
        $this->initFiles();

        // this is a bad HACK because it's NOT possible to write to php://stdin
        if ($this->getRequest() instanceof PostRequest) {
            define('HTTP_RAW_POST_DATA', $this->getRequest()->getContent());
        }
        
        $_SERVER['DOCUMENT_ROOT'] = $this->getWebappPath() . DIRECTORY_SEPARATOR . 'Web' . DIRECTORY_SEPARATOR;
        $_SERVER['FLOW_REWRITEURLS'] = 1;
        $_SERVER['FLOW_ROOTPATH'] = $this->getWebappPath();
        $_SERVER['FLOW_SAPITYPE'] = 'Web';

        $_SERVER['REDIRECT_FLOW_CONTEXT'] = 'Development';
        $_SERVER['REDIRECT_FLOW_SAPITYPE'] = 'Web';
        $_SERVER['REDIRECT_FLOW_ROOTPATH'] = '/opt/appserver/webapps/neos/';
        $_SERVER['REDIRECT_FLOW_REWRITEURLS'] = '1';
        $_SERVER['REDIRECT_STATUS'] = '200';
        
        $context = getenv('FLOW_CONTEXT') ?: (getenv('REDIRECT_FLOW_CONTEXT') ?: 'Development');

        $bootstrap = new \TYPO3\Flow\Core\Bootstrap($context);

        ob_start();
        $bootstrap->run();
        $res->setContent(ob_get_clean());
    }
}