<?php
namespace RtxLabs\DataTransformationBundle\Tests\Binder;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Tests\OrmTestCase;
use RtxLabs\DataTransformationBundle\Binder\DoctrineBinder;
use RtxLabs\DataTransformationBundle\Binder\GetMethodBinder;
use RtxLabs\UserBundle\Entity\User;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityDummy;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\Entity\CarMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\Entity\UserMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityDummyWithoutId;
use Rotex\Sbp\CoreBundle\Tests\TestHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DoctrineBinderTest extends WebTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\Client
     */
    private $client;

    public function setUp()
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $application = new Application($kernel);

        $this->em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $reader = new AnnotationReader();
        $reader->setIgnoreNotImportedAnnotations(true);
        $reader->setEnableParsePhpImports(true);

        $metadataDriver = new AnnotationDriver(
            $reader,
            // provide the namespace of the entities you want to tests
            __DIR__.'/../Mockups/Entity'
        );

        $this->em->getConfiguration()->setMetadataDriverImpl($metadataDriver);

        $this->em->getConfiguration()->setEntityNamespaces(array(
            'RotexSbpCoreBundle' => 'Rotex\\Sbp\\CoreBundle\\Tests\\Mockups\\Entity'

        ));

        TestHelper::initDatabase($application, false);
    }

    public function testExecute()
    {
        $now = new \DateTime();

        $car = new CarMock();
        $this->em->persist($car);
        $this->em->flush();

        $data = array();
        $data["username"] = "uklawitter";
        $data["deletedAt"] = $now->getTimestamp();
        $data["deletedBy"] = null;
        $data["car"] = $car->getId();
        $data["calculated"] = 75;

        $user = new UserMock();

        DoctrineBinder::create($this->em)->bind($data)->to($user)->execute();

        $this->assertEquals($data["username"], $user->getUsername());
        $this->assertEquals($now->getTimestamp(), $user->getDeletedAt()->getTimestamp());
        $this->assertEquals($car->getId(), $user->getCar()->getId());
    }

    public function testBindFieldTo()
    {
        $data = array();
        $data["name"] = "uklawitter";

        $user = new UserMock();

        DoctrineBinder::create($this->em)
            ->bind($data)
            ->field("username", $data["name"])
            ->to($user)
            ->execute();

        $this->assertEquals($data["name"], $user->getUsername());
    }

    public function testBindFieldToOverride()
    {
        $data = array();
        $data["username"] = array("ukla", "witter");

        $user = new UserMock();

        DoctrineBinder::create($this->em)
            ->bind($data)
            ->field("username", $data["username"][0].$data["username"][1])
            ->to($user)
            ->execute();


        $this->assertEquals("uklawitter", $user->getUsername());
    }

    public function testBindFieldToClosure()
    {
        $data = array();
        $data["username"] = array("ukla", "witter");

        $user = new UserMock();

        DoctrineBinder::create($this->em)
            ->bind($data)
            ->field('username', function($userData) {
                return implode("", $userData["username"]);
            })
            ->to($user)
            ->execute();

        $this->assertEquals("uklawitter", $user->getUsername());
    }

    public function testExcept() {
        $now = new \DateTime();

        $user = new UserMock();
        $user->setUsername("uklawitter");
        $user->setDeletedAt($now->getTimestamp());

        $data = DoctrineBinder::create($this->em)
            ->bind($user)
            ->except("deletedAt")
            ->execute();

        $this->assertEquals($user->getUsername(), $data["username"]);
        $this->assertArrayNotHasKey("deletedAt", $data);
    }
}