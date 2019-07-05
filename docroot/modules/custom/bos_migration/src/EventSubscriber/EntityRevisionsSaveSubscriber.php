<?php

namespace Drupal\bos_migration\EventSubscriber;

use Drupal\bos_migration\MigrationPrepareRow;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Revisions Migration save listener/subscriber.
 */
class EntityRevisionsSaveSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::PRE_ROW_SAVE => 'migrateRowPreSave',
      MigrateEvents::POST_ROW_SAVE => 'migrateRowPostSave',
    ];
  }

  /**
   * React to a entity_revision pre-save event.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Event.
   */
  public function migrateRowPreSave(MigratePreRowSaveEvent $event) {
  }

  /**
   * React to a entity_revision migration post-save event.
   *
   * This function compares the moderation state copied during the migration to
   * the moderation state for this revision in d8 database.  It updates the d8
   * moderation state if it does not match the d7 value for this revision.
   *
   * This is necessary because the migration appears to set all moderation
   * states to "draft", possibly because the d7 workbench moderation creates
   * duplicate vids with different states and the migration only takes the
   * first when multiple moderated revsiions exist.
   *
   * We use SQL statements rather than creating and updating the entity object
   * because [a] we can, this is post_save so the database is not locked, and
   * [b] changing the entity object creates new revisions which we do not want
   * to do.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function migrateRowPostSave(MigratePostRowSaveEvent $event) {

    if ($event->getMigration()->get("migration_group") != "d7_node_revision"
      || NULL == $vid = $event->getDestinationIdValues()[0]) {
      return;
    }

    $row = $event->getRow();

    // Establish the moderation states from D7.
    if (NULL == ($workbench = $row->workbench)) {
      try {
        $nid = $row->getSource()['nid'];
        $workbench["all"] = MigrationPrepareRow::findWorkbench($nid);
        $workbench["current"] = MigrationPrepareRow::findWorkbenchCurrent($nid);
      }
      catch (Error $e) {
        $workbench_all = NULL;
      }
    }

    // Get the d7 workbench moderation info for this revision.
    $workbench_all = $workbench["all"] ?: NULL;
    $workbench_revision = $workbench_all[$vid] ?: NULL;
    $workbench_current = $workbench["current"] ?: NULL;

    if (isset($workbench_revision)) {
      $workbench_current = (object) $workbench_current;
      if (empty($workbench_revision->published) || !is_numeric($workbench_revision->published)) {
        $workbench_revision->published = 0;
      }

      // Set the status for this revision and the current revision.
      MigrationPrepareRow::setNodeStatus($workbench_revision);
      MigrationPrepareRow::setNodeStatus($workbench_current);

      // Sets the node back to the correct current revision.
      MigrationPrepareRow::setCurrentRevision($workbench_current);

      // The `d7_node:xxx` migration will have imported the latest node.
      //
      // The d7 workbench_moderation maintains its own versioning
      // allowing a node_revision to have multiple moderation_states over
      // time - whereas d8 content moderation links its status with the
      // node_revision, making a new revision when the moderation state
      // changes.
      // The effect of this is that for any node revision, only the first
      // workbench_moderation state is migrated, and this state is usually
      // "draft".
      // So, the revision ond node need their moderation state to be updated.
      // Set the status for this revision and the current revision.
      // Sets the moderation state for this revision and the current revision.
      MigrationPrepareRow::setModerationState($workbench_revision);
      MigrationPrepareRow::setModerationState($workbench_current);

      // Set the moderation_state revision back to current revision.
      MigrationPrepareRow::setCurrentModerationRevision($workbench_current);

    }
  }

  /**
   * Delete.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Std.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function migrateRowPostSaveXx(MigratePostRowSaveEvent $event) {

    if ($event->getMigration()->get("migration_group") != "d7_node_revision"
      || NULL == $vid = $event->getDestinationIdValues()[0]) {
      return;
    }

    // Load the revision that has been imported.
    if (NULL != $revision_d8 = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadRevision($vid)) {

      $row = $event->getRow();

      // Establish the moderation states from D7.
      if (NULL == ($workbench_d7 = $row->workbench)) {
        try {
          $vid = $row->getSource()['vid'];
          $workbench_d7 = MigrationPrepareRow::findWorkbench($vid);
        }
        catch (Error $e) {
          $workbench_d7 = NULL;
        }
      }

      if (empty($workbench_d7['published']) || !is_numeric($workbench_d7['published'])) {
        $workbench_d7['published'] = 0;
      }

      if (isset($workbench_d7)) {
        $nid = $revision_d8->id();

        // The `d7_node:xxx` migration will have imported the latest node.
        //
        // The d7 workbench_moderation maintains its own versioning
        // allowing a node_revision to have multiple moderation_states over
        // time - whereas d8 content moderation links its status with the
        // node_revision, making a new revision when the moderation state
        // changes.
        // The effect of this is that for any node revision, only the first
        // workbench_moderation state is migrated, and this state is usually
        // "draft".
        // So, the revision ond node need their moderation state to be updated.
        MigrationPrepareRow::setModerationState($vid, $workbench_d7['state']);

        // If revision is "published" i.e status = 1 and content_mod = published
        // then update revision and node accordingly.
        // This does not run because the "published" node is usully the one
        // already migrated by `d7_node:xxxx` migration.
        if ($workbench_d7['published'] == 1) {
          // This revision is the published one, so place in the node table
          // and set its status to 1.
          MigrationPrepareRow::setNodeStatus($vid, $workbench_d7['published']);
        }

        // If the last workbench state for this revision is marked "current"
        // update the node and workbench tables.
        if ($workbench_d7['is_current'] == 1) {
          // This is almost never true, because the current revision is usually
          // the revision migrated with `d7_node:xxx` migration.
          MigrationPrepareRow::setCurrentRevision($workbench_d7["nid"], $vid);
          MigrationPrepareRow::setCurrentModerationRevision($workbench_d7["nid"], $vid);
        }
        else {
          // Find the D7 revision marked as published (status=1 and
          // moderation_state="published") and mark that revision as current
          // in D8.
          $current = MigrationPrepareRow::findWorkbenchCurrent($workbench_d7["nid"]);
          if (empty($current['published']) || !is_numeric($current['published'])) {
            $current['published'] = 0;
          }
          MigrationPrepareRow::setCurrentRevision($current["nid"], $current["vid"]);
          MigrationPrepareRow::setCurrentModerationRevision($current["nid"], $current["vid"]);
        }

        // Find the D7 revision for this node which is published and make sure
        // it is published in D8 as well.
        if ($pubset = MigrationPrepareRow::findWorkbenchPublished($nid)) {
          MigrationPrepareRow::setModerationState($pubset["vid"], "published");
        }
      }

    }
  }

}