<?php


namespace AppBundle\Command;


use AppBundle\Entity\Client;
use AppBundle\Entity\Contract;
use AppBundle\Entity\ContractItem;
use AppBundle\Entity\ContractItemType;
use AppBundle\Entity\ContractStatus;
use AppBundle\Entity\Position;
use AppBundle\Entity\ResidentQuestionnaire;
use AppBundle\Entity\ShelterHistory;
use AppBundle\Entity\ShelterStatus;
use Application\Sonata\UserBundle\Entity\Group;
use Application\Sonata\UserBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTestDataCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    protected function configure()
    {
        $this->setName('homeless:create_test_data')
            ->setDescription("Создание тестовых данных")
            ->addOption("clear", null, InputOption::VALUE_NONE,
                "Удалить все тестовые данные");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initDependencies();
        $this->initLogging($output, 'hello');

        if (!$input->getOption('clear')) {
            $users = $this->createUsers(10);
            $clients = $this->createClients($users, 1000);
            $this->createQnrs($clients, 3);
        } else {
            $users = $this->findTestUsers();
            $clients = $this->findClients($users);
            $this->removeQnrs($clients);
            $this->removeClients($clients);
            $this->removeUsers($users);
        }
    }


    protected function initDependencies()
    {
        $this->entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * @param OutputInterface $output
     * @param string $loggerName
     * @throws Exception
     */
    protected function initLogging(OutputInterface $output, $loggerName)
    {
        $this->logger = new Logger($loggerName);
        $this->logger->pushHandler(new StreamHandler(
            $this->getContainer()->getParameter('kernel.logs_dir') . '/create_test_data.log'
        ));
        $this->logger->pushHandler(new ConsoleHandler($output));
        // если указан -vvv, показываем на экране и в логе SQL запросы
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(new DbalLogger($this->logger));
        }
    }

    /**
     * @param $int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function createUsers($int)
    {
        $users = [];

        $defaultPosition = $this->entityManager->find(Position::class, 2);
        $defaultCreator = $this->entityManager->find(User::class, 1);
        $defaultGroup = $this->entityManager->find(Group::class, 1);
        for ($i = 0; $i < $int; ++$i) {
            $user = new User();
            $user->setPosition($defaultPosition);
            $user->setCreatedBy($defaultCreator);
            $user->setUsername("test user $i");
            $user->setUsernameCanonical("test user $i");
            $user->setEmail("test_email_$i@example.com");
            $user->setEmailCanonical("test_email_$i@example.com");
            $user->setEnabled(true);
            $user->setPlainPassword("password");
            $user->setLastLogin(new \DateTime());
            $user->setLocked(false);
            $user->setExpired(false);
            $user->setRoles([]);
            $user->setCredentialsExpired(false);
            $user->setCreatedAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime());
            $user->setLastname("test user $i");
            $user->setGender('u');
            $user->setGroups([$defaultGroup]);
            $this->entityManager->persist($user);
            $users[] = $user;
        }
        $this->entityManager->flush();

        return $users;
    }

    private function findTestUsers()
    {
        return $this->entityManager->createQuery(/* @lang DQL */"
            SELECT u
            FROM Application\Sonata\UserBundle\Entity\User u
            WHERE u.username LIKE 'test user %'
        ")->getResult();
    }

    /**
     * @param $users
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\DBAL\DBALException
     */
    private function removeUsers($users)
    {
        $this->entityManager->getConnection()->exec("
            UPDATE service_type SET created_by_id = NULL WHERE created_by_id = 3
        ");
        foreach ($users as $user) {
            /**
             * @var User $user
             */
            $user->setUpdatedBy(null);
        }
        $this->entityManager->flush();
        foreach ($users as $user) {
            /**
             * @var User $user
             */
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    /**
     * @param $users
     * @param $int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function createClients($users, $int)
    {
        $defaultContractStatus = $this->entityManager->find(ContractStatus::class, 1);
        $defaultContractItemType = $this->entityManager->find(ContractItemType::class, 31);
        $defaultShelterStatus = $this->entityManager->find(ShelterStatus::class, 1);
        $clients = [];
        foreach ($users as $user) {
            /**
             * @var User $user
             */
            for ($i = 0; $i < $int; ++$i) {
                $client = new Client();
                $client->setCreatedBy($user);
                $client->setPhotoName("7f1b19ad09b9f68b6ae008b0a545c801abb4dc62.jpeg");
                $client->setBirthDate(new \DateTime("1988-01-02"));
                $client->setFirstname("test");
                $client->setLastname("u".$user->getId()."c$i");
                $client->setCreatedAt(new \DateTime());
                $client->setUpdatedAt(new \DateTime());
                $client->setIsHomeless(true);
                $this->entityManager->persist($client);

                $contract = new Contract();
                $contract->setClient($client);
                $contract->setStatus($defaultContractStatus);
                $contract->setCreatedBy($user);
                $contract->setUpdatedBy($user);
                $contract->setNumber("1");
                $contract->setDateFrom(new \DateTime("2020-01-01"));
                $contract->setCreatedAt(new \DateTime());
                $contract->setUpdatedAt(new \DateTime());

                $contractItem = new ContractItem();
                $contractItem->setType($defaultContractItemType);
                $contractItem->setCreatedBy($user);
                $contractItem->setCreatedAt(new \DateTime());
                $contract->addItem($contractItem);
                $this->entityManager->persist($contract);

                $shelterHistory = new ShelterHistory();
                $shelterHistory->setClient($client);
                $shelterHistory->setStatus($defaultShelterStatus);
                $shelterHistory->setContract($contract);
                $shelterHistory->setCreatedBy($user);
                $shelterHistory->setCreatedAt(new \DateTime());
                $this->entityManager->persist($shelterHistory);

                $clients[] = $client;
            }
        }
        $this->entityManager->flush();

        return $clients;
    }

    private function findClients(array $users)
    {
        $clients = [];

        foreach ($users as $user) {
            /**
             * @var User $user
             */
            $uclients = $this->entityManager->createQuery(/* @lang DQL */ "
                SELECT c FROM AppBundle\Entity\Client c WHERE c.createdBy = :user
            ")->setParameter('user', $user)->getResult();
            $clients = array_merge($clients, $uclients);
        }
        return $clients;
    }

    /**
     * @param array $users
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function removeClients(array $clients)
    {
        foreach ($clients as $client) {
            $shelterHistories = $this->entityManager->createQuery(/* @lang DQL */"
                SELECT s
                FROM AppBundle\Entity\ShelterHistory s
                WHERE s.client = :client
            ")->setParameter('client', $client)->getResult();
            foreach ($shelterHistories as $history) {
                $this->entityManager->remove($history);
            }

            $contracts = $this->entityManager->createQuery(/* @lang DQL */"
                SELECT c
                FROM AppBundle\Entity\Contract c
                WHERE c.client = :client
            ")->setParameter('client', $client)->getResult();
            foreach ($contracts as $contract) {
                $this->entityManager->remove($contract);
            }
            $this->entityManager->createQuery(/* @lang DQL */"
                DELETE FROM AppBundle\Entity\ViewedClient vc
                WHERE vc.client = :client
            ")->setParameter('client', $client)->execute();
            $this->entityManager->remove($client);
        }
        $this->entityManager->flush();
    }

    /**
     * @param $clients
     * @param $int
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createQnrs($clients, $int)
    {
        foreach ($clients as $client) {
            /**
             * @var Client $client
             */
            for ($i = 0; $i < $int; ++$i) {
                $qnr = new ResidentQuestionnaire();
                $qnr->setClient($client);
                $qnr->setTypeId(1);
                $qnr->setIsDwelling(0);
                $qnr->setIsWork(0);
                $qnr->setIsWorkOfficial(0);
                $qnr->setIsWorkConstant(0);
                $qnr->setReasonForPetitionIds([1,2,3,4,5]);
                $this->entityManager->persist($qnr);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param array $clients
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function removeQnrs(array $clients)
    {
        foreach ($clients as $client) {
            $qnrs = $this->entityManager->createQuery(/* @lang DQL */"
                SELECT qnr FROM AppBundle\Entity\ResidentQuestionnaire qnr WHERE qnr.client = :client
            ")->setParameter('client', $client)->getResult();
            foreach ($qnrs as $qnr) {
                $this->entityManager->remove($qnr);
            }
        }
        $this->entityManager->flush();
    }
}
