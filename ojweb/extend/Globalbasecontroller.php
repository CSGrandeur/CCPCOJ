<?php
use think\Controller;
class Globalbasecontroller extends Controller
{
    var $OJ_MODE;
    var $OJ_STATUS;
    var $OJ_OPEN_OI;
    var $OJ_OPEN_ARCHIVE;
    var $OJ_NAME;
    var $OJ_SSO;
    var $OJ_SCLIENT_ID;
    var $ICP_RECORD;
    var $GA_CODE;
    var $GIT_DISCUSSION;
    var $OJ_MODE_ALLOW_MODULE;
    var $OJ_SESSION_PREFIX;
    var $OJ_ADMIN;
    // for exp sys mode
    var $ALLOW_WA_INFO;
    var $ALLOW_TEST_DOWNLOAD;
    var $PLAGIARISM_SCORE;
    //////////
    var $module;
    var $action;
    var $controller;
    var $isAdmin = false;

    public function _initialize()
    {
        $this->OJMode();
        $this->InitController();
    }
    public function InitController() {}
    public function OJMode()
    {
        $this->OJ_MODE = config('OJ_ENV.OJ_MODE');
        $this->OJ_STATUS = config('OJ_ENV.OJ_STATUS');
        $this->OJ_OPEN_OI = config('OJ_ENV.OJ_OPEN_OI');
        $this->OJ_OPEN_ARCHIVE = config('OJ_ENV.OJ_OPEN_ARCHIVE');
        $this->OJ_ADMIN = config('OjAdmin.' . $this->OJ_MODE);
        $this->OJ_NAME = config('OJ_ENV.OJ_NAME');
        $this->OJ_SSO = config('OJ_ENV.OJ_SSO');
        if($this->OJ_SSO === 0 || strtolower($this->OJ_SSO) === 'false') {
            $this->OJ_SSO = false;
        }
        $this->OJ_SCLIENT_ID = config('OJ_ENV.OJ_SCLIENT_ID');
        $this->ICP_RECORD = config('OJ_ENV.ICP_RECORD');
        $this->GA_CODE = config('OJ_ENV.GA_CODE');
        $this->GIT_DISCUSSION = config('OJ_ENV.GIT_DISCUSSION');
        $this->isAdmin = IsAdmin();
        // for expsys mode
        $this->ALLOW_WA_INFO = $this->OJ_STATUS == 'exp' && config('OJ_ENV.ALLOW_WA_INFO');
        $this->ALLOW_TEST_DOWNLOAD = $this->OJ_STATUS == 'exp' && config('OJ_ENV.ALLOW_TEST_DOWNLOAD');
        $this->PLAGIARISM_SCORE = config('OJ_ENV.PLAGIARISM_SCORE');
        $this->assign('ALLOW_WA_INFO', $this->ALLOW_WA_INFO);
        $this->assign('ALLOW_TEST_DOWNLOAD', $this->ALLOW_TEST_DOWNLOAD);
        $this->assign('PLAGIARISM_SCORE', $this->PLAGIARISM_SCORE);
        //////////
        $this->assign('OJ_MODE', $this->OJ_MODE);
        $this->assign('OJ_STATUS', $this->OJ_STATUS);
        $this->assign('OJ_OPEN_OI', $this->OJ_OPEN_OI);
        $this->assign('OJ_OPEN_ARCHIVE', $this->OJ_OPEN_ARCHIVE);
        $this->assign('OJ_NAME', $this->OJ_NAME);
        $this->assign('OJ_SSO', $this->OJ_SSO);
        $this->assign('OJ_SCLIENT_ID', $this->OJ_SCLIENT_ID);
        $this->assign('ICP_RECORD', $this->ICP_RECORD);
        $this->assign('GA_CODE', $this->GA_CODE);
        $this->assign('GIT_DISCUSSION', $this->GIT_DISCUSSION);
        $this->assign('isAdmin', $this->isAdmin);
        $this->assign('ojAdminList', $this->OJ_ADMIN['OJ_ADMIN_LIST']);
        $this->module = strtolower($this->request->module());
        $this->action = strtolower($this->request->action());
        $this->controller = strtolower($this->request->controller());
        $this->assign('module', $this->module);
        $this->assign('controller', $this->controller);
        $this->assign('action', $this->action);
        $this->OJ_SESSION_PREFIX = config('OJ_ENV.OJ_SESSION') . '_';
        $this->assign('OJ_SESSION_PREFIX', $this->OJ_SESSION_PREFIX);
        $this->assign('OJ_ADDITION_LINK', config('OJ_ENV.OJ_ADDITION_LINK'));
        $this->OJ_MODE_ALLOW_MODULE = config('OjMode.OJ_MODE_ALLOW_MODULE');
        if(!in_array($this->module, $this->OJ_MODE_ALLOW_MODULE[$this->OJ_MODE][$this->OJ_STATUS]) && !IsAdmin('administrator')) {
            $this->redirect('/' . $this->OJ_MODE_ALLOW_MODULE[$this->OJ_MODE][$this->OJ_STATUS][0]);
        }
    }
}