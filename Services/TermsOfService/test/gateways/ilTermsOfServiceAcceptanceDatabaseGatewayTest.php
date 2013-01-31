<?php
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/TermsOfService/classes/class.ilTermsOfServiceAcceptanceDatabaseGateway.php';
require_once 'Services/TermsOfService/classes/class.ilTermsOfServiceAcceptanceEntity.php';

/**
 * @author  Michael Jansen <mjansen@databay.de>
 * @version $Id$
 */
class ilTermsOfServiceAcceptanceDatabaseGatewayTest extends PHPUnit_Framework_TestCase
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
		$database = $this->getMock('ilDB');
		$gateway  = new ilTermsOfServiceAcceptanceDatabaseGateway($database);

		$this->assertInstanceOf('ilTermsOfServiceAcceptanceDatabaseGateway', $gateway);
	}

	/**
	 * 
	 */
	public function testAcceptanceIsTrackedAndCreatesANewTermsOfServicesVersion()
	{
		$entity = new ilTermsOfServiceAcceptanceEntity();
		$entity->setUserId(666);
		$entity->setLanguage('de');
		$entity->setPathToFile('/path/to/file');
		$entity->setSignedText('PHP Unit');
		$entity->setTimestamp(time());
		$entity->setHash(md5($entity->getSignedText()));

		$expected_id = 4711;

		$database = $this->getMock('ilDB');
		$result   = $this->getMockBuilder('MDB2_BufferedResult_mysqli')->disableOriginalConstructor()->getMock();

		$database->expects($this->once())->method('queryF')->with('SELECT id FROM tos_versions WHERE hash = %s AND lng = %s', array('text', 'text'), array($entity->getHash(), $entity->getLanguage()))->will($this->returnValue($result));
		$database->expects($this->once())->method('numRows')->with($result)->will($this->returnValue(0));
		$database->expects($this->once())->method('nextId')->with('tos_versions')->will($this->returnValue($expected_id));

		$expectedVersions = array(
			'id'   => array('integer', $expected_id),
			'lng'  => array('text', $entity->getLanguage()),
			'path' => array('text', $entity->getPathToFile()),
			'text' => array('text', $entity->getSignedText()),
			'hash' => array('text', $entity->getHash()),
			'ts'   => array('integer', $entity->getTimestamp())
		);
		$expectedTracking = array(
			'tosv_id' => array('integer', $expected_id),
			'usr_id'  => array('integer', $entity->getUserId()),
			'ts'      => array('integer', $entity->getTimestamp())
		);
		$database->expects($this->exactly(2))->method('insert')->with(
			$this->logicalOr('tos_versions', 'tos_acceptance_track'),
			$this->logicalOr($expectedVersions, $expectedTracking)
		);

		$gateway = new ilTermsOfServiceAcceptanceDatabaseGateway($database);
		$gateway->save($entity);
	}

	/**
	 *
	 */
	public function testAcceptanceIsTrackedAndRefersToAnExistingTermsOfServicesVersion()
	{
		$entity = new ilTermsOfServiceAcceptanceEntity();
		$entity->setUserId(666);
		$entity->setLanguage('de');
		$entity->setPathToFile('/path/to/file');
		$entity->setSignedText('PHP Unit');
		$entity->setTimestamp(time());
		$entity->setHash(md5($entity->getSignedText()));

		$expected_id = 4711;

		$database = $this->getMock('ilDB');
		$result   = $this->getMockBuilder('MDB2_BufferedResult_mysqli')->disableOriginalConstructor()->getMock();

		$database->expects($this->once())->method('queryF')->with('SELECT id FROM tos_versions WHERE hash = %s AND lng = %s', array('text', 'text'), array($entity->getHash(), $entity->getLanguage()))->will($this->returnValue($result));
		$database->expects($this->once())->method('numRows')->with($result)->will($this->returnValue(1));
		$database->expects($this->once())->method('fetchAssoc')->with($result)->will($this->returnValue(array('id' => $expected_id)));

		$expectedTracking = array(
			'tosv_id' => array('integer', $expected_id),
			'usr_id'  => array('integer', $entity->getUserId()),
			'ts'      => array('integer', $entity->getTimestamp())
		);
		$database->expects($this->once())->method('insert')->with('tos_acceptance_track', $expectedTracking);

		$gateway = new ilTermsOfServiceAcceptanceDatabaseGateway($database);
		$gateway->save($entity);
	}

	/**
	 * 
	 */
	public function testLatestEntityIsLoaded()
	{
		$entity = new ilTermsOfServiceAcceptanceEntity();

		$expected = array(
			'id'          => 4711,
			'usr_id'      => 6,
			'lng'         => 'de',
			'path'        => '/path/to/file',
			'text'        => 'PHP Unit',
			'accepted_ts' => time()
		);

		$database = $this->getMock('ilDB');
		$database->expects($this->once())->method('fetchAssoc')->will($this->onConsecutiveCalls($expected));
		$gateway = new ilTermsOfServiceAcceptanceDatabaseGateway($database);
		$gateway->loadCurrentOfUser($entity);

		$this->assertEquals($expected['id'], $entity->getId());
		$this->assertEquals($expected['usr_id'], $entity->getUserId());
		$this->assertEquals($expected['lng'], $entity->getLanguage());
		$this->assertEquals($expected['path'], $entity->getPathToFile());
		$this->assertEquals($expected['text'], $entity->getSignedText());
		$this->assertEquals($expected['accepted_ts'], $entity->getTimestamp());
	}
}