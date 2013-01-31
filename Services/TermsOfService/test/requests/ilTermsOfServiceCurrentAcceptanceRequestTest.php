<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/TermsOfService/classes/class.ilTermsOfServiceCurrentAcceptanceRequest.php';

/**
 * @author  Michael Jansen <mjansen@databay.de>
 * @version $Id$
 */
class ilTermsOfServiceCurrentAcceptanceRequestTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var bool
	 */
	protected $backupGlobals = false;

	/**
	 *
	 */
	public function setUp()
	{
		require_once 'Services/PHPUnit/classes/class.ilUnitUtil.php';
		ilUnitUtil::performInitialisation();
	}

	/**
	 *
	 */
	public function testInstanceCanBeCreated()
	{
		$request = new ilTermsOfServiceCurrentAcceptanceRequest();
		$this->assertInstanceOf('ilTermsOfServiceCurrentAcceptanceRequest', $request);
		$this->assertInstanceOf('ilTermsOfServiceBaseRequest', $request);
		$this->assertInstanceOf('ilTermsOfServiceRequest', $request);
	}

	/**
	 *
	 */
	public function testUserIdIsInitiallyEmpty()
	{
		$request = new ilTermsOfServiceCurrentAcceptanceRequest();
		$this->assertEmpty($request->getUserId());
	}

	/**
	 *
	 */
	public function testRequestShouldReturnUserIdWhenUserIdIsSet()
	{
		$exptected = 1337;

		$request = new ilTermsOfServiceCurrentAcceptanceRequest();
		$request->setUserId($exptected);
		$this->assertEquals($exptected, $request->getUserId());
	}

	/**
	 *
	 */
	public function testEntityFactoryIsInitiallyEmpty()
	{
		$request = new ilTermsOfServiceCurrentAcceptanceRequest();
		$this->assertEmpty($request->getEntityFactory());
	}

	/**
	 *
	 */
	public function testRequestShouldReturnEntityFactoryWhenEntityFactoryIsSet()
	{
		$request = new ilTermsOfServiceCurrentAcceptanceRequest();
		$factory = $this->getMock('ilTermsOfServiceEntityFactory');
		$request->setEntityFactory($factory);
		$this->assertEquals($factory, $request->getEntityFactory());
	}
}