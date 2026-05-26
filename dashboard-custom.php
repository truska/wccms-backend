<?php
    // --- Fetch latest administered drugs ---
    $selectdrug = "
        SELECT 
            m.id AS medicine_id,
            m.drug AS drug_id,
            d.name AS drug_name,
            d.dose AS standard_dose,
            m.dose AS administered_dose,
            m.staff AS staff,
            m.text AS notes,
            CONCAT(s.firstname, ' ', s.surname) AS staff_name,
            m.timegiven AS given_time
        FROM oli_medicine AS m
        LEFT JOIN oli_drugs AS d ON d.id = m.drug
        LEFT JOIN oli_staff AS s ON s.id = m.staff
        WHERE m.timegiven >= NOW() - INTERVAL 2 DAY
        ORDER BY m.timegiven DESC
    ";
    $drugRows = cms_db_fetch_all($selectdrug);
?>

<div class='col-12'>
  <h3>Drugs Administered</h3>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead class="table-dark">
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Drug</th>
          <th scope="col">Given At</th>
          <th scope="col">Name</th>
          <th scope="col">Dose</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($drugRows as $row) : 
          $notes = trim($row['notes']);
          $hasNotes = !empty($notes);
        ?>
        <tr>
          <td><?= htmlspecialchars($row['medicine_id']); ?></td>
          <td><?= htmlspecialchars($row['drug_name']); ?></td>
          <td><?= date('d-m-Y H:i', strtotime($row['given_time'])); ?></td>
          <td><?= htmlspecialchars($row['staff_name']); ?></td>
          <td><?= htmlspecialchars($row['administered_dose']); ?></td>
          <td>
            <?php if ($hasNotes): ?>
              <button type="button" 
                      class="btn btn-sm btn-primary" 
                      data-bs-toggle="modal" 
                      data-bs-target="#notesModal" 
                      data-notes="<?= htmlspecialchars($notes, ENT_QUOTES); ?>">
                Notes
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-secondary" disabled>Notes</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- --- Bootstrap Modal for Notes --- -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notesModalLabel">Medicine Notes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="notesContent">
        <!-- HTML notes will be injected here -->
      </div>
    </div>
  </div>
</div>

<!-- --- Modal Script --- -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const notesModal = document.getElementById('notesModal');
  notesModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const notes = button.getAttribute('data-notes');
    const notesContent = document.getElementById('notesContent');
    // Display as HTML, not text
    notesContent.innerHTML = notes ? notes : '<em>No notes available.</em>';
  });
});
</script>
