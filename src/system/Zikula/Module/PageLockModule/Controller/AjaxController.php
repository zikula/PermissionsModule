<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\PageLockModule\Controller;

use UserUtil;
use ModUtil;
use Zikula\Core\Response\Ajax\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route; // used in annotations - do not remove
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method; // used in annotations - do not remove

/**
 * @Route("/ajax")
 *
 * Ajax controllers for the pagelock module
 */
class AjaxController extends \Zikula_Controller_AbstractAjax
{
    /**
     * @Route("/refresh", options={"expose"=true})
     * @Method("POST")
     *
     * refresh a page lock
     *
     * @param Request $request
     *
     * @return AjaxResponse containing { hasLock: bool, message: string, lockedBy: string, message:string|null }
     */
    public function refreshpagelockAction(Request $request)
    {
        $this->checkAjaxToken();
        $lockName = $request->request->get('lockname');

        $uname = UserUtil::getVar('uname');

        $lockInfo = ModUtil::apiFunc('ZikulaPageLockModule', 'user', 'requireLock',
            array('lockName' => $lockName,
                'sessionId' => $request->getSession()->getId(),
                'lockedByTitle' => $uname,
                'lockedByIPNo' => $request->server->get('REMOTE_ADDR')));

        if (!$lockInfo['hasLock']) {
            $lockInfo['message'] = $this->__('Error! Lock broken!');
        } else {
            $lockInfo['message'] = null;
        }

        return new AjaxResponse($lockInfo);
    }

    /**
     * @Route("/check", options={"expose"=true})
     * @Method("POST")
     *
     * change a page lock
     *
     * @param Request $request
     *
     * @return AjaxResponse containing { hasLock: bool, message: string, lockedBy: string, message:string|null }
     */
    public function checkpagelockAction(Request $request)
    {
        $this->checkAjaxToken();
        $lockName = $request->request->get('lockname');

        $uname = UserUtil::getVar('uname');

        $lockInfo = ModUtil::apiFunc('ZikulaPageLockModule', 'user', 'requireLock',
            array('lockName' => $lockName,
                'sessionId' => $request->getSession()->getId(),
                'lockedByTitle' => $uname,
                'lockedByIPNo' => $request->server->get('REMOTE_ADDR')));

        if (!$lockInfo['hasLock']) {
            $lockInfo['message'] = $this->__('Error! Lock broken!');
        } else {
            $lockInfo['message'] = null;
        }

        return new AjaxResponse($lockInfo);
    }
}
