<?php

final class ArcanistRefQuery extends Phobject {

  private $repositoryAPI;
  private $conduitEngine;

  private $refs;
  private $hardpoints;

  public function setRefs(array $refs) {
    assert_instances_of($refs, 'ArcanistRef');
    $this->refs = $refs;
    return $this;
  }

  public function getRefs() {
    return $this->refs;
  }

  public function setRepositoryAPI(ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function setConduitEngine(ArcanistConduitEngine $conduit_engine) {
    $this->conduitEngine = $conduit_engine;
    return $this;
  }

  public function getConduitEngine() {
    return $this->conduitEngine;
  }

  public function needHardpoints(array $hardpoints) {
    $this->hardpoints = $hardpoints;
    return $this;
  }

  public function execute() {
    $refs = $this->getRefs();

    if ($this->refs === null) {
      throw new PhutilInvalidStateException('setRefs');
    }

    if ($this->hardpoints === null) {
      throw new PhutilInvalidStateException('needHardpoints');
    }

    $api = $this->getRepositoryAPI();
    $all_loaders = ArcanistHardpointLoader::getAllLoaders();

    $loaders = array();
    foreach ($all_loaders as $loader_key => $loader) {
      if (!$loader->canLoadRepositoryAPI($api)) {
        continue;
      }

      $loaders[$loader_key] = id(clone $loader)
        ->setQuery($this);
    }

    foreach ($this->hardpoints as $hardpoint) {
      $load = array();
      $need = array();
      $has_hardpoint = false;
      foreach ($refs as $ref_key => $ref) {
        if (!$ref->hasHardpoint($hardpoint)) {
          continue;
        }

        $has_hardpoint = true;

        if ($ref->hasAttachedHardpoint($hardpoint)) {
          continue;
        }

        foreach ($loaders as $loader_key => $loader) {
          if (!$loader->canLoadRef($ref)) {
            continue;
          }

          if (!$loader->canLoadHardpoint($ref, $hardpoint)) {
            continue;
          }

          $load[$loader_key][$ref_key] = $ref;
        }

        $need[$ref_key] = $ref_key;
      }

      if ($refs && !$has_hardpoint) {
        throw new Exception(
          pht(
            'No ref in query has hardpoint "%s".',
            $hardpoint));
      }

      $vectors = array();
      foreach ($need as $ref_key) {
        $ref = $refs[$ref_key];
        if ($ref->isVectorHardpoint($hardpoint)) {
          $vectors[$ref_key] = $ref_key;
          $ref->attachHardpoint($hardpoint, array());
        }
      }

      foreach ($load as $loader_key => $loader_refs) {
        $loader_refs = array_select_keys($loader_refs, $need);

        $loader = $loaders[$loader_key];
        $data = $loader->loadHardpoints($loader_refs, $hardpoint);

        foreach ($data as $ref_key => $value) {
          $ref = $refs[$ref_key];
          if (isset($vectors[$ref_key])) {
            $ref->appendHardpoint($hardpoint, $value);
          } else {
            unset($need[$ref_key]);
            $ref->attachHardpoint($hardpoint, $value);
          }
        }
      }

      foreach ($vectors as $ref_key) {
        unset($need[$ref_key]);
      }

      if ($need) {
        throw new Exception(
          pht(
            'Nothing could attach data to hardpoint "%s" for ref "%s".',
            $hardpoint,
            $refs[head($need)]->getRefIdentifier()));
      }
    }

    return $refs;
  }

}
