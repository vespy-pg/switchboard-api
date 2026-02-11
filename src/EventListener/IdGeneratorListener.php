<?php

namespace App\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;

class IdGeneratorListener
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();
        $connection = $entityManager->getConnection();
        $classMetadata = $entityManager->getClassMetadata(get_class($entity));

        // Check if ID is already set
        if ($classMetadata->getIdentifierValues($entity)) {
            return;
        }

        $identifier = $classMetadata->getIdentifier()[0] ?? null;
        if (!$identifier) {
            return;
        }

        // Check if the column type is uuid or string (UUID)
        $columnType = $classMetadata->fieldMappings[$identifier]['type'] ?? null;

        if ($columnType === 'uuid') {
            // Generate UUID using PostgreSQL's gen_random_uuid()
            $result = $connection->executeQuery('SELECT gen_random_uuid() AS id')->fetchAssociative();
            if ($result && isset($result['id'])) {
                $setter = 'set' . ucfirst($identifier);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($result['id']);
                }
            }
        } else {
            // Handle numeric IDs with sequence functions (legacy approach)
            $tableName = $classMetadata->getTableName();
            $columnName = $classMetadata->fieldMappings[$identifier]['columnName'];

            $sequenceFunction = $classMetadata->fieldMappings[$identifier]['options']['default'] ??
                sprintf("sys_id_next_get('rss.%s_seq')", $tableName . '_' . $columnName);

            $query = sprintf('SELECT %s AS next_id', $sequenceFunction);
            $result = $connection->executeQuery($query)->fetchAssociative();

            if ($result && isset($result['next_id'])) {
                $setter = 'set' . ucfirst($identifier);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($result['next_id']);
                }
            } else {
                throw new \RuntimeException(sprintf('Failed to generate a new ID for entity of type %s.', get_class($entity)));
            }
        }
    }
}
