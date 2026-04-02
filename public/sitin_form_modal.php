<!-- ─── Sit-in Form Modal ─── -->
<div class="modal-overlay" id="sitinModal">
    <div class="modal-box">
        <div class="modal-header">
            <span>Sit In Form</span>
            <button class="modal-close" onclick="closeModal('sitinModal')">×</button>
        </div>
        <form method="POST" action="admin_dashboard.php">
            <div class="modal-body">
                <div class="modal-field">
                    <label for="sitin_id_number">ID Number</label>
                    <input type="text" id="sitin_id_number" name="sitin_id_number" placeholder="Default" readonly
                        required>
                </div>
                <div class="modal-field">
                    <label for="sitin_student_name">Student Name</label>
                    <input type="text" id="sitin_student_name" name="sitin_student_name" placeholder="Default"
                        readonly required>
                </div>
                <div class="modal-field">
                    <label for="sitin_purpose">Purpose</label>
                    <select id="sitin_purpose" name="sitin_purpose" required>
                        <option value="" selected disabled>Select purpose</option>
                        <option value="Python">Python</option>
                        <option value="C#">C#</option>
                        <option value="PHP">PHP</option>
                        <option value="Java">Java</option>
                        <option value="C++">C++</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label for="sitin_lab">Lab</label>
                    <select id="sitin_lab" name="sitin_lab" required>
                        <option value="" selected disabled>Select lab</option>
                        <option value="524">524</option>
                        <option value="525">526</option>
                        <option value="526">528</option>
                        <option value="527">530</option>
                        <option value="527">542</option>
                        <option value="527">544</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label for="sitin_sessions">Remaining Sessions</label>
                    <input type="text" id="sitin_sessions" readonly value="30">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn btn-cancel" onclick="closeModal('sitinModal')">Close</button>
                <button type="submit" class="modal-btn btn-confirm">Sit In</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openSitInForm() {
        document.getElementById('sitin_id_number').value = '';
        document.getElementById('sitin_student_name').value = '';
        document.getElementById('sitin_purpose').selectedIndex = 0;
        document.getElementById('sitin_lab').selectedIndex = 0;
        document.getElementById('sitin_sessions').value = 30;
        openModal('sitinModal');
    }

    // ─── Auto-fill sit-in form when ID is entered ───
    document.addEventListener('DOMContentLoaded', function() {
        const sitinIdInput = document.getElementById('sitin_id_number');
        if (sitinIdInput && !sitinIdInput.dataset.listenerAttached) {
            let sitinTimeout;
            sitinIdInput.addEventListener('input', function () {
                clearTimeout(sitinTimeout);
                const idNum = this.value.trim();
                if (idNum.length < 1) {
                    document.getElementById('sitin_student_name').value = '';
                    document.getElementById('sitin_sessions').value = 30;
                    return;
                }
                sitinTimeout = setTimeout(() => {
                    fetch('admin_dashboard.php?ajax_search=1&q=' + encodeURIComponent(idNum))
                        .then(r => r.json())
                        .then(data => {
                            const match = data.find(s => s.id_number === idNum);
                            if (match) {
                                document.getElementById('sitin_student_name').value = match.first_name + ' ' + match.last_name;
                                document.getElementById('sitin_sessions').value = match.remaining_sessions ?? 30;
                            } else {
                                document.getElementById('sitin_student_name').value = '';
                                document.getElementById('sitin_sessions').value = 30;
                            }
                        });
                }, 400);
            });
            sitinIdInput.dataset.listenerAttached = 'true';
        }
    });
</script>
