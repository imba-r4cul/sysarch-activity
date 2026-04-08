<!-- ─── Search Student Modal ─── -->
<div class="modal-overlay" id="searchModal">
    <div class="modal-box">
        <div class="modal-header">
            <span>Search Student</span>
            <button class="modal-close" onclick="closeModal('searchModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="search-input-row">
                <input type="text" id="modalSearchInput" placeholder="Search by name or ID number...">
                <button type="button" onclick="doSearch()">Search</button>
            </div>
            <div class="search-results" id="searchResults">
                <p class="no-results">Enter a query to search for students.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // ─── Search ───
    let latestSearchData = [];

    function selectStudentForSitIn(index) {
        const student = latestSearchData[index];
        if (!student) return;

        document.getElementById('sitin_id_number').value = student.id_number || '';
        document.getElementById('sitin_student_name').value = ((student.first_name || '') + ' ' + (student.last_name || '')).trim();
        document.getElementById('sitin_sessions').value = student.remaining_sessions ?? 30;
        document.getElementById('sitin_purpose').selectedIndex = 0;
        document.getElementById('sitin_lab').selectedIndex = 0;

        closeModal('searchModal');
        openModal('sitinModal');
    }

    function doSearch() {
        const q = document.getElementById('modalSearchInput').value.trim();
        const container = document.getElementById('searchResults');
        if (!q) {
            container.innerHTML = '<p class="no-results">Enter a query to search for students.</p>';
            return;
        }
        container.innerHTML = '<p class="no-results">Searching...</p>';

        fetch('admin_dashboard.php?ajax_search=1&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                latestSearchData = data;
                if (data.length === 0) {
                    container.innerHTML = '<p class="no-results">No students found.</p>';
                    return;
                }
                let html = '<table class="search-results-table"><thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Action</th></tr></thead><tbody>';
                data.forEach((student, idx) => {
                    html += '<tr><td>' + (student.id_number || '') + '</td><td>' + (student.first_name || '') + ' ' + (student.last_name || '') + '</td><td>' + (student.course || '') + '</td><td><button type="button" class="search-action-btn" onclick="selectStudentForSitIn(' + idx + ')">Use</button></td></tr>';
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = '<p class="no-results">Error searching. Please try again.</p>';
            });
    }

    // Search on Enter key
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('modalSearchInput');
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') doSearch();
            });
        }
    });
</script>
