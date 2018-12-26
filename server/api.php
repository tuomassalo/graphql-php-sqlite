<?php
require_once __DIR__ . '../../vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use GraphQL\Server\StandardServer;

try {
  $photoType = new ObjectType([
    'name' => 'photo',
    'fields' => [
      'imagePath' => ['type' => Type::string()],
      'name' => ['type' => Type::string()],
    ]
  ]);

  $queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
      'photos' => [
        'type' => Type::listOf($photoType),
        'args' => [
          'q' => [
            'type' => Type::string(),
            'defaultValue' => null
          ],
        ],
        'resolve' => function ($root, $args) {
          try {
            error_log(json_encode([$root, $args]));
            $conds = ['RKVersion.nonRawMasterUuid = RKMaster.uuid'];
            $vals = [];

            if($args['q']) {
              $conds[] = 'RKVersion.name LIKE :q';
              $vals[] = [':q', '%' . $args['q'] . '%']; // NB: no escaping for _ or %
            }

            error_log(json_encode([$conds, $vals]));

            $db = new SQLite3('../../photos.db');
            error_log(2);
            $q = '
              SELECT RKMaster.imagePath, RKVersion.name
              FROM RKVersion, RKMaster
              WHERE
              '. implode(" AND ", $conds) . '
              LIMIT 50
            ';
            error_log($q);

            $stmt = $db->prepare($q);
            foreach($vals as $v) {
            $stmt->bindValue($v[0], $v[1]);
            }
            $result = $stmt->execute();

            $resultArr = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
              $resultArr[] = $row;
            }

            // error_log(json_encode([resultArr => $resultArr]));

            return $resultArr;
          } catch (\Exception $e) {
            error_log("INTERNAL ERROR:");
            error_log($e);
            StandardServer::send500Error('INTERNAL ERROR');
          }
        }
      ],
    ],
  ]);

  // See docs on schema options:
  // http://webonyx.github.io/graphql-php/type-system/schema/#configuration-options
  $schema = new Schema([
    'query' => $queryType,
    // 'mutation' => $mutationType,
  ]);

  // See docs on server options:
  // http://webonyx.github.io/graphql-php/executing-queries/#server-configuration-options
  $server = new StandardServer([
      'schema' => $schema
  ]);

  $server->handleRequest();

} catch (\Exception $e) {
    StandardServer::send500Error($e);
}
