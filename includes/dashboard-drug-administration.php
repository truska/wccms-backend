<?php
/**
 * Drug administration table for the dashboard Welcome tab.
 * Uses the existing PDO connection from wccms/includes/boot.php.
 * Mobile-responsive: shows Drug, Given at, Name on all devices.
 * ID, Dose, and Action visible on desktop only; collapsible on mobile.
 */

$drugRows = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sql = <<<'SQL'
SELECT
  m.id AS medicine_id,
  m.drug AS drug_id,
  d.name AS drug_name,
  d.dose AS standard_dose,
  m.dose AS administered_dose,
  m.staff AS staff_id,
  m.text AS notes,
  CONCAT(s.firstname, ' ', s.surname) AS staff_name,
  m.timegiven AS given_time
FROM oli_medicine AS m
LEFT JOIN oli_drugs AS d ON d.id = m.drug
LEFT JOIN oli_staff AS s ON s.id = m.staff
WHERE m.timegiven >= NOW() - INTERVAL 2 DAY
ORDER BY m.timegiven DESC
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $drugRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $drugRows = [];
    }
}
?>
<div class="cms-card">
  <h2 class="h4 mb-3">Drug Administration</h2>
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th class="d-none d-md-table-cell">ID</th>
          <th>Drug</th>
          <th>Given at</th>
          <th>Name</th>
          <th class="d-none d-md-table-cell">Dose</th>
          <th class="d-none d-md-table-cell">Action</th>
          <th class="d-md-none">Details</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($drugRows) === 0): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">Drug Area</td>
          </tr>
        <?php else: ?>
          <?php foreach ($drugRows as $idx => $row): ?>
            <?php
              $notes = trim((string) ($row['notes'] ?? ''));
              $hasNotes = $notes !== '';
              $givenAt = '';
              if (!empty($row['given_time'])) {
                  $timestamp = strtotime($row['given_time']);
                  if ($timestamp !== false) {
                      $givenAt = date('d-m-Y H:i', $timestamp);
                  }
              }
              $collapseId = 'drugDetailsCollapse_' . htmlspecialchars((string) $idx);
            ?>
            <tr>
              <td class="d-none d-md-table-cell"><?php echo cms_h((string) ($row['medicine_id'] ?? '')); ?></td>
              <td><?php echo cms_h((string) ($row['drug_name'] ?? '')); ?></td>
              <td><?php echo cms_h($givenAt); ?></td>
              <td><?php echo cms_h((string) ($row['staff_name'] ?? '')); ?></td>
              <td class="d-none d-md-table-cell"><?php echo cms_h((string) ($row['administered_dose'] ?? '')); ?></td>
              <td class="d-none d-md-table-cell">
                <?php if ($hasNotes): ?>
                  <button type="button" class="btn btn-sm btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#drugNotesModal"
                    data-notes="<?php echo cms_h($notes, ENT_QUOTES); ?>">
                    Notes
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-secondary" disabled>Notes</button>
                <?php endif; ?>
              </td>
              <td class="d-md-none">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                  Expand
                </button>
              </td>
            </tr>
            <!-- Mobile collapse row -->
            <tr class="d-md-none">
              <td colspan="4" class="p-0 border-0">
                <div class="collapse" id="<?php echo $collapseId; ?>">
                  <div class="card card-body p-3 bg-light">
                    <dl class="row mb-0 small">
                      <dt class="col-sm-4">ID</dt>
                      <dd class="col-sm-8"><?php echo cms_h((string) ($row['medicine_id'] ?? '')); ?></dd>
                      <dt class="col-sm-4">Dose</dt>
                      <dd class="col-sm-8"><?php echo cms_h((string) ($row['administered_dose'] ?? '')); ?></dd>
                      <dt class="col-sm-4">Action</dt>
                      <dd class="col-sm-8">
                        <?php if ($hasNotes): ?>
                          <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#drugNotesModal"
                            data-notes="<?php echo cms_h($notes, ENT_QUOTES); ?>">
                            Notes
                          </button>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-secondary" disabled>Notes</button>
                        <?php endif; ?>
                      </dd>
                    </dl>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="drugNotesModal" tabindex="-1" aria-labelledby="drugNotesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="drugNotesModalLabel">Medicine Notes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="drugNotesContent">
        <p class="text-muted mb-0">No notes available.</p>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var notesModal = document.getElementById('drugNotesModal');
  if (!notesModal) {
    return;
  }

  notesModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var notes = button ? button.getAttribute('data-notes') : '';
    var notesContent = document.getElementById('drugNotesContent');
    if (notesContent) {
      if (notes) {
        notesContent.textContent = notes;
      } else {
        notesContent.innerHTML = '<p class="text-muted mb-0">No notes available.</p>';
      }
    }
  });
});
</script>
