/* ============================================
   BIRTH RECORDS - PAGE SCRIPTS
   Municipality of San Julian, Eastern Samar
============================================ */

// ── Search ──
$('#btnSearch').on('click', function () {
    const q = $('#searchInput').val().trim();
    const y = $('#yearFilter').val();
    let url = 'birth_records.php?';
    if (q) url += 'search=' + encodeURIComponent(q) + '&';
    if (y) url += 'year=' + encodeURIComponent(y);
    window.location.href = url;
});

$('#searchInput').on('keypress', function (e) {
    if (e.which === 13) $('#btnSearch').click();
});

// ── Modal Helpers ──
function openModal(id)  { $('#' + id).addClass('active'); $('body').css('overflow', 'hidden'); }
function closeModal(id) { $('#' + id).removeClass('active'); $('body').css('overflow', ''); }

// Close modal on overlay click
$('.modal-overlay').on('click', function (e) {
    if ($(e.target).hasClass('modal-overlay')) {
        $(this).removeClass('active');
        $('body').css('overflow', '');
        closeCamera();
    }
});

// ── Show Alert ──
function showAlert(type, msg) {
    $('#pageAlert').removeClass('show success danger')
        .addClass('show ' + type)
        .html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg);
    setTimeout(() => $('#pageAlert').removeClass('show'), 4000);
}

// ── Add Record ──
$('#btnAddRecord').on('click', function () {
    $('#recordForm')[0].reset();
    $('#record_id').val('');
    $('#formAction').val('add');
    $('#modalTitle').html('<i class="fas fa-plus"></i> Add Birth Record');
    clearFile();
    openModal('recordModal');
});

// ── File Upload ──
$('#docFile').on('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { showAlert('danger', 'File size must not exceed 5MB.'); return; }
    showFilePreview(file);
});

// Drag and Drop
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) { $('#docFile')[0].files = e.dataTransfer.files; showFilePreview(file); }
});

function showFilePreview(file) {
    $('#previewName').text(file.name);
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { $('#previewImg').attr('src', e.target.result).show(); };
        reader.readAsDataURL(file);
    } else {
        $('#previewImg').attr('src', '').hide();
    }
    $('#previewBox').show();
    $('#capturedImageData').val('');
}

function clearFile() {
    $('#docFile').val('');
    $('#previewBox').hide();
    $('#previewImg').attr('src', '');
    $('#previewName').text('');
    $('#capturedImageData').val('');
}

// ── Camera ──
let mediaStream = null;

$('#btnOpenCamera').on('click', function () {
    openModal('cameraModal');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            mediaStream = stream;
            document.getElementById('cameraFeed').srcObject = stream;
        })
        .catch(err => {
            showAlert('danger', 'Cannot access camera: ' + err.message);
            closeModal('cameraModal');
        });
});

$('#btnCapture').on('click', function () {
    const video  = document.getElementById('cameraFeed');
    const canvas = document.getElementById('captureCanvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    $('#capturedImageData').val(dataUrl);
    $('#previewImg').attr('src', dataUrl).show();
    $('#previewName').text('camera_capture.jpg');
    $('#previewBox').show();
    clearFile();
    closeCamera();
    closeModal('cameraModal');
    showAlert('success', 'Photo captured successfully!');
});

function closeCamera() {
    if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
    $('#cameraFeed')[0].srcObject = null;
    closeModal('cameraModal');
}

// ── Save Record (AJAX) ──
$('#btnSaveRecord').on('click', function () {
    const form = $('#recordForm')[0];
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

    const formData = new FormData(form);

    $.ajax({
        url: 'php/birth_records.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                closeModal('recordModal');
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert('danger', res.message);
            }
        },
        error: function () { showAlert('danger', 'Server error. Please try again.'); },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Record'); }
    });
});

// ── View Record ──
function viewRecord(id) {
    $('#viewModalBody').html('<p style="text-align:center;color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>');
    openModal('viewModal');

    $.ajax({
        url: 'php/birth_records.php?action=get&id=' + id,
        type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                const r = res.data;
                const fullName = [r.child_first_name, r.child_middle_name, r.child_last_name].filter(Boolean).join(' ');
                let docHtml = r.document_path
                    ? `<a href="${r.document_path}" target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;"><i class="fas fa-file"></i> View Document</a>`
                    : '<span style="color:#aaa;font-size:0.82rem;">No document uploaded.</span>';
                if (r.document_path && /\.(jpg|jpeg|png)$/i.test(r.document_path)) {
                    docHtml = `<img src="${r.document_path}" class="view-doc" alt="Document"/>` + docHtml;
                }
                $('#viewModalBody').html(`
                    <div class="view-grid">
                        <div class="view-field"><div class="view-label">Registry Number</div><div class="view-value">${r.registry_number}</div></div>
                        <div class="view-field"><div class="view-label">Date Registered</div><div class="view-value">${formatDate(r.date_registered)}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Child's Full Name</div><div class="view-value" style="font-size:1rem;font-weight:700;">${fullName}</div></div>
                        <div class="view-field"><div class="view-label">Sex</div><div class="view-value">${r.child_sex}</div></div>
                        <div class="view-field"><div class="view-label">Date of Birth</div><div class="view-value">${formatDate(r.date_of_birth)}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Place of Birth</div><div class="view-value">${r.place_of_birth}</div></div>
                        <div class="view-field"><div class="view-label">Father's Name</div><div class="view-value">${r.father_name || '—'}</div></div>
                        <div class="view-field"><div class="view-label">Mother's Name</div><div class="view-value">${r.mother_name || '—'}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Remarks</div><div class="view-value">${r.remarks || '—'}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Added By</div><div class="view-value">${r.added_by || '—'}</div></div>
                        <div class="view-field" style="grid-column:span 2"><div class="view-label">Scanned Document</div><div class="view-value">${docHtml}</div></div>
                    </div>
                `);
            } else {
                $('#viewModalBody').html('<p style="color:#c0392b;">Failed to load record.</p>');
            }
        }
    });
}

// ── Edit Record ──
function editRecord(id) {
    $.ajax({
        url: 'php/birth_records.php?action=get&id=' + id,
        type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                const r = res.data;
                $('#record_id').val(r.id);
                $('#formAction').val('edit');
                $('#modalTitle').html('<i class="fas fa-pen"></i> Edit Birth Record');
                $('#registry_number').val(r.registry_number);
                $('#date_registered').val(r.date_registered);
                $('#child_first_name').val(r.child_first_name);
                $('#child_middle_name').val(r.child_middle_name);
                $('#child_last_name').val(r.child_last_name);
                $('#child_sex').val(r.child_sex);
                $('#date_of_birth').val(r.date_of_birth);
                $('#place_of_birth').val(r.place_of_birth);
                $('#father_name').val(r.father_name);
                $('#mother_name').val(r.mother_name);
                $('#remarks').val(r.remarks);
                clearFile();
                openModal('recordModal');
            }
        }
    });
}

// ── Delete Record ──
let deleteId = null;
function deleteRecord(id, regNum) {
    deleteId = id;
    $('#deleteRegNum').text(regNum);
    openModal('deleteModal');
}

$('#btnConfirmDelete').on('click', function () {
    if (!deleteId) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    $.ajax({
        url: 'php/birth_records.php',
        type: 'POST',
        data: { action: 'delete', record_id: deleteId },
        dataType: 'json',
        success: function (res) {
            closeModal('deleteModal');
            if (res.status === 'success') {
                showAlert('success', res.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert('danger', res.message);
            }
        },
        complete: function () { $btn.prop('disabled', false).html('<i class="fas fa-trash"></i> Yes, Delete'); }
    });
});

// ── Date Format Helper ──
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}