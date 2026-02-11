<?php

namespace App\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use App\Entity\Customer;

class CustomerIdSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        dd('lalala');
        return [Events::prePersist];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        dump('dupa');
        // Check if the entity is of type Customer
        if (!$entity instanceof Customer) {
            return;
        }

        $em = $args->getObjectManager();
        $conn = $em->getConnection();

        // Fetch the next ID from the sequence
        $stmt = $conn->prepare("SELECT sys_id_next_get('rss.tbl_customer_customer_id_seq'::character varying) AS id");
        $stmt->execute();
        $result = $stmt->fetchAssociative();

        if ($result && isset($result['id'])) {
            $entity->setId($result['id']);
        }
    }
}
