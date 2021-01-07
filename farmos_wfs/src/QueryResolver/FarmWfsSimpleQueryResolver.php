<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\farmos_wfs\FarmWfsQueryFactory;

class FarmWfsSimpleQueryResolver {

  protected $queryFactory;

  public function __construct(FarmWfsQueryFactory $query_factory) {
    $this->queryFactory = $query_factory;
  }

  /**
   * Retrieves an array of asset ids by geometry type.
   */
  function resolve_query(string $asset_type, array $geometry_types) {
    $asset_query = $this->queryFactory->create_query($asset_type, $geometry_types);

    $result = $asset_query->execute();

    return $result->fetchCol(0);
  }
}
