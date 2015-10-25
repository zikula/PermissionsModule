<?php
/**
 * Copyright Zikula Foundation 2015 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\PermissionsModule\Tests\Api;

use Zikula\PermissionsModule\Api\PermissionApi;
use Zikula\PermissionsModule\Tests\Api\Fixtures\StubPermissionRepository;

class PermissionApiTest extends \PHPUnit_Framework_TestCase
{
    private $permRepo;
    private $user;
    private $userRepo;
    private $session;
    private $translator;

    /**
     * VariableApiTest setUp.
     */
    public function setUp()
    {
        $this->permRepo = new StubPermissionRepository();
        $this->user = $this
            ->getMockBuilder('Zikula\UsersModule\Entity\UserEntity')
            ->disableOriginalConstructor()
            ->getMock();
        $this->userRepo = $this
            ->getMockBuilder('Zikula\UsersModule\Entity\Repository\UserRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $this->userRepo
            ->method('findByUids')
            ->with($this->anything())
            ->will($this->returnCallback(function (array $uids) /*use ($user)*/ {
                $groups = [PermissionApi::UNREGISTERED_USER_GROUP => []];
                if (in_array(1, $uids)) { // guest
                    $groups = [1 => []]; // gid => $group
                } elseif (in_array(2, $uids)) { // admin
                    $groups = [1 => [], 2 => []]; // gid => $group
                }
                $this->user
                    ->method('getGroups')
                    ->will($this->returnValue($groups));

                return [$this->user]; // must return an array of users.
            }));
        $this->session = $this
            ->getMockBuilder('Zikula_Session')
            ->disableOriginalConstructor()
            ->getMock();
        $this->translator = $this
            ->getMockBuilder('Zikula\Common\Translator\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $this->translator
            ->method('__')
            ->willReturnArgument(0);
    }

    /**
     * Call protected/private method of the api class.
     *
     * @param PermissionApi $api
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     * @return mixed Method return.
     */
    private function invokeMethod($api, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($api));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($api, $parameters);
    }

    /**
     * @covers       PermissionApi::setGroupPermsForUser
     * @dataProvider permProvider
     */
    public function testSetGroupPermsForUser($uid, $perms)
    {
        $api = new PermissionApi($this->permRepo, $this->userRepo, $this->session, $this->translator);
        $this->invokeMethod($api, 'setGroupPermsForUser', [$uid]);
        $this->assertEquals($perms, $api->getGroupPerms($uid));
    }

    /**
     * @covers       PermissionApi::getSecurityLevel
     * @dataProvider secLevelProvider
     */
    public function testGetSecurityLevel($uid, $component, $instance, $expectedLevel)
    {
        $api = new PermissionApi($this->permRepo, $this->userRepo, $this->session, $this->translator);
        $this->invokeMethod($api, 'setGroupPermsForUser', [$uid]);
        $perms = $api->getGroupPerms($uid);
        $this->assertEquals($expectedLevel, $this->invokeMethod($api, 'getSecurityLevel', [$perms, $component, $instance]));
    }

    /**
     * @covers       PermissionApi::hasPermission
     * @dataProvider uidProvider
     */
    public function testHasPermission($component, $instance, $level, $uid, $result)
    {
        $this->session
            ->method('get')
            ->with($this->equalTo('uid'))
            ->will($this->returnValue($uid));
        $api = new PermissionApi($this->permRepo, $this->userRepo, $this->session, $this->translator);
        $this->assertEquals($result, $api->hasPermission($component, $instance, $level, $uid));
    }

    /**
     * @covers       PermissionApi::accessLevelNames
     * @dataProvider accessLevelNamesProvider
     */
    public function testAccessLevelNames($expectedText, $level)
    {
        $api = new PermissionApi($this->permRepo, $this->userRepo, $this->session, $this->translator);
        $this->assertEquals($expectedText, $api->accessLevelNames($level));
    }

    public function permProvider()
    {
        return [
            [2/* SITE ADMIN */, [
                ["component" => ".*",
                    "instance" => ".*",
                    "level" => ACCESS_ADMIN],
                ["component" => "ExtendedMenublock:.*:.*",
                    "instance" => "1:1:.*",
                    "level" => ACCESS_NONE],
                ["component" => ".*",
                    "instance" => ".*",
                    "level" => ACCESS_COMMENT],
            ]],
            [PermissionApi::UNREGISTERED_USER, [
                ["component" => "ExtendedMenublock:.*:.*",
                    "instance" => "1:1:.*",
                    "level" => ACCESS_NONE],
                ["component" => "ExtendedMenublock:.*:.*",
                    "instance" => "1:(1|2|3):.*",
                    "level" => ACCESS_NONE],
                ["component" => ".*",
                    "instance" => ".*",
                    "level" => ACCESS_READ],
            ]],
            [null/* ALL perms ALL USERS */, [
                [
                    ["component" => "ExtendedMenublock:.*:.*",
                        "instance" => "1:1:.*",
                        "level" => ACCESS_NONE],
                    ["component" => "ExtendedMenublock:.*:.*",
                        "instance" => "1:(1|2|3):.*",
                        "level" => ACCESS_NONE],
                    ["component" => ".*",
                        "instance" => ".*",
                        "level" => ACCESS_READ],
                ]
            ]],
            [1/* GUEST */, [
                ["component" => "ExtendedMenublock:.*:.*",
                    "instance" => "1:1:.*",
                    "level" => ACCESS_NONE],
                ["component" => ".*",
                    "instance" => ".*",
                    "level" => ACCESS_COMMENT],
            ]],
        ];
    }

    public function secLevelProvider()
    {
        return [
            [2, '.*', '.*', ACCESS_ADMIN],
            [1, '.*', '.*', ACCESS_COMMENT],
            [PermissionApi::UNREGISTERED_USER, '.*', '.*', ACCESS_READ],
            [null, '.*', '.*', ACCESS_INVALID],

            [2, 'ExtendedMenublock::', '1:1:', ACCESS_ADMIN],
            [1, 'ExtendedMenublock::', '1:1:', ACCESS_NONE],
            [PermissionApi::UNREGISTERED_USER, 'ExtendedMenublock::', '1:1:', ACCESS_NONE],
            [null, 'ExtendedMenublock::', '1:1:', ACCESS_INVALID],

            [2, 'ExtendedMenublock::', '1:2:', ACCESS_ADMIN],
            [1, 'ExtendedMenublock::', '1:2:', ACCESS_COMMENT],
            [PermissionApi::UNREGISTERED_USER, 'ExtendedMenublock::', '1:2:', ACCESS_NONE],
            [null, 'ExtendedMenublock::', '1:2:', ACCESS_INVALID],
        ];
    }

    public function uidProvider()
    {
        return [
            ['.*', '.*', ACCESS_OVERVIEW, 2, true],
            ['.*', '.*', ACCESS_READ, 2, true],
            ['.*', '.*', ACCESS_COMMENT, 2, true],
            ['.*', '.*', ACCESS_MODERATE, 2, true],
            ['.*', '.*', ACCESS_EDIT, 2, true],
            ['.*', '.*', ACCESS_ADD, 2, true],
            ['.*', '.*', ACCESS_DELETE, 2, true],
            ['.*', '.*', ACCESS_ADMIN, 2, true],

            ['.*', '.*', ACCESS_OVERVIEW, 1, true],
            ['.*', '.*', ACCESS_READ, 1, true],
            ['.*', '.*', ACCESS_COMMENT, 1, true],
            ['.*', '.*', ACCESS_MODERATE, 1, false],
            ['.*', '.*', ACCESS_EDIT, 1, false],
            ['.*', '.*', ACCESS_ADD, 1, false],
            ['.*', '.*', ACCESS_DELETE, 1, false],
            ['.*', '.*', ACCESS_ADMIN, 1, false],

            ['.*', '.*', ACCESS_OVERVIEW, null, false],
            ['.*', '.*', ACCESS_READ, null, false],
            ['.*', '.*', ACCESS_COMMENT, null, false],
            ['.*', '.*', ACCESS_MODERATE, null, false],
            ['.*', '.*', ACCESS_EDIT, null, false],
            ['.*', '.*', ACCESS_ADD, null, false],
            ['.*', '.*', ACCESS_DELETE, null, false],
            ['.*', '.*', ACCESS_ADMIN, null, false],

            ['ExtendedMenublock::', '1:1:', ACCESS_OVERVIEW, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_READ, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_COMMENT, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_MODERATE, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_EDIT, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADD, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_DELETE, 2, true],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADMIN, 2, true],

            ['ExtendedMenublock::', '1:1:', ACCESS_OVERVIEW, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_READ, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_COMMENT, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_MODERATE, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_EDIT, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADD, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_DELETE, 1, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADMIN, 1, false],

            ['ExtendedMenublock::', '1:1:', ACCESS_OVERVIEW, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_READ, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_COMMENT, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_MODERATE, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_EDIT, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADD, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_DELETE, null, false],
            ['ExtendedMenublock::', '1:1:', ACCESS_ADMIN, null, false],

            ['ExtendedMenublock::', '1:2:', ACCESS_OVERVIEW, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_READ, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_COMMENT, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_MODERATE, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_EDIT, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADD, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_DELETE, 2, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADMIN, 2, true],

            ['ExtendedMenublock::', '1:2:', ACCESS_OVERVIEW, 1, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_READ, 1, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_COMMENT, 1, true],
            ['ExtendedMenublock::', '1:2:', ACCESS_MODERATE, 1, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_EDIT, 1, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADD, 1, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_DELETE, 1, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADMIN, 1, false],

            ['ExtendedMenublock::', '1:2:', ACCESS_OVERVIEW, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_READ, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_COMMENT, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_MODERATE, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_EDIT, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADD, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_DELETE, null, false],
            ['ExtendedMenublock::', '1:2:', ACCESS_ADMIN, null, false],

            ['ExtendedMenublock::', '1:3:', ACCESS_OVERVIEW, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_READ, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_COMMENT, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_MODERATE, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_EDIT, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADD, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_DELETE, 2, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADMIN, 2, true],

            ['ExtendedMenublock::', '1:3:', ACCESS_OVERVIEW, 1, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_READ, 1, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_COMMENT, 1, true],
            ['ExtendedMenublock::', '1:3:', ACCESS_MODERATE, 1, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_EDIT, 1, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADD, 1, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_DELETE, 1, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADMIN, 1, false],

            ['ExtendedMenublock::', '1:3:', ACCESS_OVERVIEW, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_READ, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_COMMENT, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_MODERATE, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_EDIT, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADD, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_DELETE, null, false],
            ['ExtendedMenublock::', '1:3:', ACCESS_ADMIN, null, false],
        ];
    }

    public function accessLevelNamesProvider()
    {
        return [
            ['Invalid', ACCESS_INVALID],
            ['No access', ACCESS_NONE],
            ['Overview access', ACCESS_OVERVIEW],
            ['Read access', ACCESS_READ],
            ['Comment access', ACCESS_COMMENT],
            ['Moderate access', ACCESS_MODERATE],
            ['Edit access', ACCESS_EDIT],
            ['Add access', ACCESS_ADD],
            ['Delete access', ACCESS_DELETE],
            ['Admin access', ACCESS_ADMIN],
        ];
    }
}
