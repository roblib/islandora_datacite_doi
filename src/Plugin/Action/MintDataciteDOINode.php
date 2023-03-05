<?php

namespace Drupal\islandora_datacite_doi\Plugin\Action;

use Drupal\node\NodeInterface;

/**
 * Mints a Datacite DOI for noes.
 *
 * @Action(
 *   id = "dgi_actions_mint_datacite_doi_node",
 *   label = @Translation("Mint a Datacite DOI for Nodes."),
 *   type = "node"
 * )
 */
class MintDataciteDOINode extends MintDataciteDOI {

  /**
   * {@inheritdoc}
   */
public function execute($entity = NULL): void {
  // Workaround for bug where context condition for non-nodes was evaluating to TRUE.
    if ($entity instanceof NodeInterface) {
      parent::execute($entity);
    }
  }

}
