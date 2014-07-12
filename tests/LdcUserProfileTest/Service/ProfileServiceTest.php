<?php
/**
 * LdcUserProfile
 *
 * @link      http://github.com/adamlundrigan/LdcUserProfile for the canonical source repository
 * @copyright Copyright (c) 2014 Adam Lundrigan & Contributors
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace LdcUserProfileTest\Service;

use LdcUserProfile\Service\ProfileService;
use Zend\Form\Element\Text;
use Zend\Stdlib\Hydrator\ObjectProperty;
use Zend\Form\FormInterface;
use Zend\EventManager\EventManager;

class ProfileServiceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->service = new ProfileService();
    }

    public function testGetSetFormPrototype()
    {
        $mock = \Mockery::mock('Zend\Form\FormInterface');

        $this->service->setFormPrototype($mock);
        $this->assertSame($mock, $this->service->getFormPrototype());
    }

    public function testRegisterExtension()
    {
        $ext = \Mockery::mock('LdcUserProfile\Extensions\AbstractExtension');
        $ext->shouldReceive('getName')->andReturn('testext');

        $this->service->registerExtension($ext);
        $this->assertArrayHasKey('testext', $this->service->getExtensions());

        return $ext;
    }

    public function testRegisterExtensionFiresEvents()
    {
        $mockEventManager = new TriggerCountingEventManager();
        $this->service->setEventManager($mockEventManager);

        $this->testRegisterExtension();

        $this->assertEquals(array(
            'LdcUserProfile\Service\ProfileService::registerExtension.pre'  => 1,
            'LdcUserProfile\Service\ProfileService::registerExtension.post' => 1,
        ), $mockEventManager->triggeredEventCount);
    }

    public function testRegisterExtensionRejectsInvalidExtension()
    {
        $this->setExpectedException('PHPUnit_Framework_Error');
        $this->service->registerExtension(new \stdClass());
    }

    public function testRegisterExtensionRejectsNullExtension()
    {
        $this->setExpectedException('PHPUnit_Framework_Error');
        $this->service->registerExtension(null);
    }

    public function testUnregisterExtensionByInstance()
    {
        $ext = $this->testRegisterExtension();

        $this->service->unregisterExtension($ext);
        $this->assertArrayNotHasKey('testext', $this->service->getExtensions());
    }

    public function testUnregisterExtensionByName()
    {
        $ext = $this->testRegisterExtension();

        $this->service->unregisterExtension($ext->getName());
        $this->assertArrayNotHasKey('testext', $this->service->getExtensions());
    }

    public function testUnregisterExtensionFiresEvents()
    {
        $ext = $this->testRegisterExtension();

        $mockEventManager = new TriggerCountingEventManager();
        $this->service->setEventManager($mockEventManager);

        $this->service->unregisterExtension($ext->getName());

        $this->assertEquals(array(
            'LdcUserProfile\Service\ProfileService::unregisterExtension.pre'  => 1,
            'LdcUserProfile\Service\ProfileService::unregisterExtension.post' => 1,
        ), $mockEventManager->triggeredEventCount);
    }

    public function testHasExtensionByName()
    {
        $ext = $this->testRegisterExtension();

        $this->assertTrue($this->service->hasExtension($ext->getName()));
    }

    public function testHasExtensionByInstance()
    {
        $ext = $this->testRegisterExtension();

        $this->assertTrue($this->service->hasExtension($ext));
    }

    public function testSaveCallsSaveOnEachRegsiteredExtension()
    {
        $payload = new \stdClass();

        $mock = $this->testRegisterExtension();
        $mock->shouldReceive('save')->once()->withArgs(array($payload))->andReturn(true);

        $this->assertTrue($this->service->save($payload));
    }

    public function testSaveCallsSaveOnEachRegsiteredExtensionAndReturnsFalseWhenAnExtensionFails()
    {
        $payload = new \stdClass();

        $mock = $this->testRegisterExtension();
        $mock->shouldReceive('save')->once()->withArgs(array($payload))->andReturn(false);

        $this->assertFalse($this->service->save($payload));
    }

    public function testSaveFiresEvents()
    {
        $mockEventManager = new TriggerCountingEventManager();
        $mockEventManager->matchingRegex = '{^LdcUserProfile\\\\Service\\\\ProfileService::save}is';
        $this->service->setEventManager($mockEventManager);

        $this->testSaveCallsSaveOnEachRegsiteredExtension();

        $this->assertEquals(array(
            'LdcUserProfile\Service\ProfileService::save.pre'  => 1,
            'LdcUserProfile\Service\ProfileService::save.post' => 1,
        ), $mockEventManager->triggeredEventCount);
    }

    public function testConstructFormForUser()
    {
        $mockUserData = new \stdClass();
        $mockUserData->test = 'hi';

        $mockFieldset = \Mockery::mock('Zend\Form\Fieldset[getName,setName]');
        $mockFieldset->shouldReceive('getName')->andReturn('testext');
        $mockFieldset->shouldReceive('setName')->withArgs(array('testext'))->once();
        $mockFieldset->add(new Text('test'));
        $mockFieldset->setHydrator(new ObjectProperty());
        $mockFieldset->setObject(new \stdClass());

        $mockInputFilter = new \Zend\InputFilter\InputFilter();
        $mockInputFilter->add(new \Zend\InputFilter\Input('test'));

        $mockUser = \Mockery::mock('ZfcUser\Entity\UserInterface');

        $ext = $this->testRegisterExtension();
        $ext->shouldReceive('getFieldset')->once()->andReturn($mockFieldset);
        $ext->shouldReceive('getInputFilter')->once()->andReturn($mockInputFilter);
        $ext->shouldReceive('getObjectForUser')->withArgs(array($mockUser))->once()->andReturn($mockUserData);
        $ext->shouldReceive('getFieldsetValidationGroup')->andReturn(FormInterface::VALIDATE_ALL);

        $form = $this->service->constructFormForUser($mockUser);

        $this->assertInstanceOf('Zend\Form\FormInterface', $form);
        $this->assertTrue($form->has('testext'));
        $this->assertTrue($form->get('testext')->has('test'));
        $this->assertEquals('hi', $form->get('testext')->get('test')->getValue());
        $this->assertTrue($form->getInputFilter()->has('testext'));
        $this->assertTrue($form->getInputFilter()->get('testext')->has('test'));
    }

    public function testConstructFormForUserFiresEvents()
    {
        $mockEventManager = new TriggerCountingEventManager();
        $mockEventManager->matchingRegex = '{^LdcUserProfile\\\\Service\\\\ProfileService::constructFormForUser}is';
        $this->service->setEventManager($mockEventManager);

        $this->testConstructFormForUser();

        $this->assertEquals(array(
            'LdcUserProfile\Service\ProfileService::constructFormForUser.pre'  => 1,
            'LdcUserProfile\Service\ProfileService::constructFormForUser.extension'  => 1,
            'LdcUserProfile\Service\ProfileService::constructFormForUser.post' => 1,
        ), $mockEventManager->triggeredEventCount);
    }

    public function testConstructFormForUserObeysValidationGroupOverrides()
    {
        $mockUserData = new \stdClass();
        $mockUserData->test = 'hi';

        $mockFieldset = \Mockery::mock('Zend\Form\Fieldset[getName,setName]');
        $mockFieldset->shouldReceive('getName')->andReturn('testext');
        $mockFieldset->shouldReceive('setName')->withArgs(array('testext'))->once();
        $mockFieldset->add(new Text('test'));
        $mockFieldset->setHydrator(new ObjectProperty());
        $mockFieldset->setObject(new \stdClass());

        $mockInputFilter = new \Zend\InputFilter\InputFilter();
        $mockInputFilter->add(new \Zend\InputFilter\Input('test'));

        $mockUser = \Mockery::mock('ZfcUser\Entity\UserInterface');

        $ext = $this->testRegisterExtension();
        $ext->shouldReceive('getFieldset')->once()->andReturn($mockFieldset);
        $ext->shouldReceive('getInputFilter')->once()->andReturn($mockInputFilter);
        $ext->shouldReceive('getObjectForUser')->withArgs(array($mockUser))->once()->andReturn($mockUserData);
        $ext->shouldReceive('setFieldsetValidationGroup')->withArgs(array(array('test')))->once();
        $ext->shouldReceive('getFieldsetValidationGroup')->andReturn(array('test'))->once();

        $this->service->getModuleOptions()->setValidationGroupOverrides(array(
            'testext' => array('test')
        ));

        $form = $this->service->constructFormForUser($mockUser);

        $this->assertInstanceOf('Zend\Form\FormInterface', $form);
        $this->assertTrue($form->has('testext'));
        $this->assertTrue($form->get('testext')->has('test'));
        $this->assertEquals('hi', $form->get('testext')->get('test')->getValue());
        $this->assertTrue($form->getInputFilter()->has('testext'));
        $this->assertTrue($form->getInputFilter()->get('testext')->has('test'));
    }

    public function testConstructFormForUseIgnoresEmptyValidationGroupOverridesForAnExtension()
    {
        $mockUserData = new \stdClass();
        $mockUserData->test = 'hi';

        $mockFieldset = \Mockery::mock('Zend\Form\Fieldset[getName,setName]');
        $mockFieldset->shouldReceive('getName')->andReturn('testext');
        $mockFieldset->shouldReceive('setName')->withArgs(array('testext'))->once();
        $mockFieldset->add(new Text('test'));
        $mockFieldset->setHydrator(new ObjectProperty());
        $mockFieldset->setObject(new \stdClass());

        $mockInputFilter = new \Zend\InputFilter\InputFilter();
        $mockInputFilter->add(new \Zend\InputFilter\Input('test'));

        $mockUser = \Mockery::mock('ZfcUser\Entity\UserInterface');

        $ext = $this->testRegisterExtension();
        $ext->shouldReceive('getFieldset')->once()->andReturn($mockFieldset);
        $ext->shouldReceive('getInputFilter')->once()->andReturn($mockInputFilter);
        $ext->shouldReceive('getObjectForUser')->withArgs(array($mockUser))->once()->andReturn($mockUserData);
        $ext->shouldReceive('setFieldsetValidationGroup')->withArgs(array(array()))->once();
        $ext->shouldReceive('getFieldsetValidationGroup')->andReturn(array())->once();

        $this->service->getModuleOptions()->setValidationGroupOverrides(array(
            'testext' => array()
        ));

        $form = $this->service->constructFormForUser($mockUser);

        $this->assertInstanceOf('Zend\Form\FormInterface', $form);
        $this->assertTrue($form->has('testext'));
        $this->assertTrue($form->get('testext')->has('test'));
        $this->assertEquals('hi', $form->get('testext')->get('test')->getValue());
        $this->assertTrue($form->getInputFilter()->has('testext'));
        $this->assertTrue($form->getInputFilter()->get('testext')->has('test'));
    }

    public function testGetSetEventManager()
    {
        $mock = \Mockery::mock('Zend\EventManager\EventManagerInterface');
        $mock->shouldReceive('setIdentifiers')->withArgs(array(array(
            'LdcUserProfile\\Service\\ProfileService',
            'LdcUserProfile\\Service\\ProfileService',
        )))->andReturnNull();

        $this->service->setEventManager($mock);
        $this->assertSame($mock, $this->service->getEventManager());
    }

    public function testGetSetEventManagerAcceptsIdentifierFromInternalProperty()
    {
        $mock = \Mockery::mock('Zend\EventManager\EventManagerInterface');
        $mock->shouldReceive('setIdentifiers')->withArgs(array(array(
            'LdcUserProfile\\Service\\ProfileService',
            'LdcUserProfileTest\\Service\\ProfileServiceWithExtraEventManagerIdentifier',
            'someOtherIdentifier'
        )))->andReturnNull();

        $service = new ProfileServiceWithExtraEventManagerIdentifier();
        $service->eventIdentifier = array('someOtherIdentifier');
        $service->setEventManager($mock);
    }

    public function testGetSetEventManagerAcceptsObjectIdentifierFromInternalProperty()
    {
        $mock = \Mockery::mock('Zend\EventManager\EventManagerInterface');
        $mock->shouldReceive('setIdentifiers')->withArgs(array(array(
            'LdcUserProfile\\Service\\ProfileService',
            'LdcUserProfileTest\\Service\\ProfileServiceWithExtraEventManagerIdentifier',
            new \stdClass(),
        )))->andReturnNull();

        $service = new ProfileServiceWithExtraEventManagerIdentifier();
        $service->eventIdentifier = new \stdClass();
        $service->setEventManager($mock);
    }
}

class ProfileServiceWithExtraEventManagerIdentifier extends ProfileService
{
    public $eventIdentifier = null;
}

class TriggerCountingEventManager extends EventManager
{
    public $triggeredEventCount = array();
    public $matchingRegex = null;

    public function trigger($event, $target = null, $argv = array(), $callback = null)
    {
        if ( !empty($this->matchingRegex) && !preg_match($this->matchingRegex, $event) ) {
            return;
        }

        if ( ! isset($this->triggeredEventCount[$event]) ) {
            $this->triggeredEventCount[$event] = 0;
        }
        $this->triggeredEventCount[$event]++;
    }
}
