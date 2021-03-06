<?php
namespace RtxLabs\DataTransformationBundle\Tests\Binder;

//require_once "{$_SERVER['KERNEL_DIR']}/AppKernel.php";

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Tests\OrmTestCase;
use RtxLabs\DataTransformationBundle\Binder\DoctrineBinder;
use RtxLabs\DataTransformationBundle\Binder\GetMethodBinder;
use RtxLabs\UserBundle\Entity\User;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityDummy;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\Entity\CarMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\Entity\GroupMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\Entity\UserMock;
use RtxLabs\DataTransformationBundle\Tests\Mockups\EntityDummyWithoutId;
use RtxLabs\DataTransformationBundle\Tests\TestHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;



class DoctrineBinderTest extends WebTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    public function setUp()
    {
        $kernel = new \AppKernel('test', true);
        $kernel->boot();
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
    }

    public function testExecute()
    {
        $now = new \DateTime();

        $car = new CarMock();
        $this->em->persist($car);
        $this->em->flush();

        $data = new \stdClass();
        $data->username = "uklawitter";
        $data->deletedAt = $now->getTimestamp();
        $data->deletedBy = null;
        $data->car = $car->getId();
        $data->calculated = 75;

        $user = new UserMock();

        DoctrineBinder::create($this->em, false)->bind($data)->to($user)->execute();

        $this->assertEquals($data->username, $user->getUsername());
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

    public function testBindToObjectWithEmptyString()
    {
        $data = array("car" => "");

        $car = new CarMock();
        $user = new UserMock();
        $user->setCar($car);

        DoctrineBinder::create($this->em)
            ->bind($data)
            ->field("car")
            ->to($user)
            ->execute();

        $this->assertNull($user->getCar());
    }

    public function testBindToObjectWithBooleanEmptyString()
    {
        $data = array("isAdmin"=>false);

        $user = new UserMock();
        $user->setIsAdmin(true);

        DoctrineBinder::create($this->em)
            ->bind($data)
            ->field("isAdmin")
            ->to($user)
            ->execute();

        $this->assertFalse($user->getIsAdmin());
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

        $data = DoctrineBinder::create($this->em, false)
            ->bind($user)
            ->except("deletedAt")
            ->execute();

        $this->assertEquals($user->getUsername(), $data["username"]);
        $this->assertArrayNotHasKey("deletedAt", $data);
    }

    public function testJoin() {
        $user = new UserMock();
        $group1 = new GroupMock();
        $group2 = new GroupMock();

        $user->addGroup($group1);
        $user->addGroup($group2);

        $data = DoctrineBinder::create($this->em)
            ->bind($user)
            ->join("groups", DoctrineBinder::create($this->em))
            ->execute();

        $this->assertEquals(2, count($data["groups"]));
    }

    public function testJoinWithArray() {
        $user1 = new UserMock();
        $user2 = new UserMock();

        $group1 = new GroupMock();
        $group2 = new GroupMock();

        $user1->addGroup($group1);
        $user2->addGroup($group2);

        $users = array($user1, $user2);

        $data = DoctrineBinder::create($this->em)
            ->bind($users)
            ->join("groups", DoctrineBinder::create($this->em))
            ->execute();

        $this->assertEquals(2, count($data));
        $this->assertEquals(1, count($data[0]["groups"]));
    }

    public function testBindEmptyArray() {
        $result = DoctrineBinder::create($this->em)->bind(array())->execute();

        $this->assertEquals(array(), $result);
    }

    public function testBindWithWhitelistEmpty() {
        $now = new \DateTime();

        $bind = new UserMock();
        $bind->setUsername("uklawitter");
        $bind->setDeletedAt($now);

        $result = DoctrineBinder::create($this->em)->bind($bind)->execute();

        $this->assertEquals(0, count($result));
    }

    public function testBindWithWhitelistWithFields() {
        $now = new \DateTime();

        $bind = new UserMock();
        $bind->setUsername("uklawitter");
        $bind->setDeletedAt($now);

        $result = DoctrineBinder::create($this->em)
            ->bind($bind)
            ->field("username")
            ->execute();

        $this->assertEquals(1, count($result));
        $this->assertEquals($bind->getUsername(), $result["username"]);
    }
}
