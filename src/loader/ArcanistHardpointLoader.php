<?php

abstract class ArcanistHardpointLoader
  extends Phobject {

  private $query;
  private $conduitEngine;

  abstract public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api);
  abstract public function canLoadRef(ArcanistRef $ref);
  abstract public function canLoadHardpoint(ArcanistRef $ref, $hardpoint);
  abstract public function loadHardpoints(array $refs, $hardpoint);

  final public function setQuery(ArcanistRefQuery $query) {
    $this->query = $query;
    return $this;
  }

  final public function getQuery() {
    return $this->query;
  }

  final public function getConduitEngine() {
    return $this->getQuery()->getConduitEngine();
  }

  final protected function newQuery(array $refs) {
    return id(new ArcanistRefQuery())
      ->setRepositoryAPI($this->getQuery()->getRepositoryAPI())
      ->setConduitEngine($this->getQuery()->getConduitEngine())
      ->setRefs($refs);
  }

  final public function getLoaderKey() {
    return $this->getPhobjectClassConstant('LOADERKEY', 64);
  }

  final public static function getAllLoaders() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLoaderKey')
      ->execute();
  }

  final public function resolveCall($method, array $parameters) {
    return $this->newCall($method, $parameters)->resolve();
  }

  final public function newCall($method, array $parameters) {
    return $this->getConduitEngine()->newCall($method, $parameters);
  }

  final protected function newFutureIterator(array $futures) {
    return id(new FutureIterator($futures))
      ->limit(16);
  }

}
