
    console.log('external script load');

   $(document).ready(function() {

    // Format time function (converts 24h to 12h format)
    function formatTime(time) {
        if (!time) return '';
        
        // If time is already in 12h format, return as is
        if (time.includes('AM') || time.includes('PM')) {
            return time;
        }
        
        // Convert 24h to 12h format
        var parts = time.split(':');
        var hours = parseInt(parts[0]);
        var minutes = parts[1];
        var suffix = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12 || 12; // Convert 0 to 12 for midnight
        
        return hours + ':' + minutes + ' ' + suffix;
    }

    // Search button click handler
    $('#searchBtn').on('click', function() {
        var studentID = $('#searchInput').val().trim();
        
        if(!studentID) {
            Swal.fire({
                icon: "error",
                title: "Error!",
                text: "Please enter student ID."
            });
            return;
        } 

        $.get('/ncst_nav/config/get_schedule.php', {student_id: studentID}, function(response) {
            // Debug: Log the response
            console.log('API Response:', response);
            
            if(response.success) {    
                Swal.fire({
                    icon: "success",
                    title: "Success!",
                    text: "Student information loaded successfully.",
                    timer: 1500,
                    showConfirmButton: false
                });
                
                // Display student information
                $('#studentInfoDisplay').html(
                    `<div class="d-inline-flex">
                        <div class="mx-2" style="width: 377px">
                            <label for="readID">Student ID</label>
                            <input type="text" readonly class="form-control" id="readID">
                        </div>
                        <div class="mx-2" style="width: 377px">
                            <label for="readName">Name</label>
                            <input type="text" readonly class="form-control" id="readName">
                        </div>
                        <div class="mx-2" style="width: 400px">
                            <label for="readYear">Year</label>
                            <input type="text" readonly class="form-control" id="readYear">
                        </div>
                    </div>
                    <div class="d-inline-flex p-2">
                        <div class="mx-2" style="width: 400px">
                            <label for="readSem">Semester</label>
                            <input type="text" readonly class="form-control" id="readSem">
                        </div>
                        <div class="mx-2" style="width: 40%">
                            <label for="program">Program</label> 
                            <input type="text" readonly class="form-control" id="program" name="program">
                        </div>
                        <div class="mx-2" style="width: 40%">
                            <label for="section">Section</label> 
                            <input type="text" readonly class="form-control" id="section" name="section">
                        </div>
                    </div>`
                );

                // Populate student information
                $('#readID').val(response.student.student_id);
                $('#readName').val(response.student.first_name + ' ' + response.student.last_name);
                $('#readYear').val(response.student.year);
                $('#readSem').val(response.student.semester);
                $('#program').val(response.student.courseName);
                $('#section').val(response.student.section_name);

                // Load schedules
                loadSchedule(response.schedules);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: "Student not found"
                });
                $('#studentInfoDisplay').html('');
                $('#scheduleTable').html('');
            }
        }, 'json').fail(function() {
            Swal.fire({
                icon: "error",
                title: "Error!",
                text: "Failed to fetch student data. Please try again."
            });
        });
    });

    // Load schedule function
    function loadSchedule(schedules) {
        if(!schedules.length) {
            $('#scheduleTable').html(`<div class="alert alert-info">No schedule found for this student.</div>`);
            return;
        }

        var display = `<table class="table table-bordered table-striped table-hover" id="scheduleDataTable">
            <thead class="table-primary">
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Professor</th>
                    <th>Room</th>
                    <th>Day</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>`;
            
        schedules.forEach(function(sch) {
            display += `<tr>
                <td>${sch.subject_code}</td>
                <td>${sch.subject_name}</td>
                <td>${sch.teacher_name}</td>
                <td>${sch.room_id}</td>
                <td>${sch.day_of_week}</td>
                <td>${formatTime(sch.time_start)} - ${formatTime(sch.time_end)}</td>
            </tr>`;
        });
        
        display += `</tbody></table>`;
        $('#scheduleTable').html(display);
        
        // Initialize DataTables for better viewing experience
        setTimeout(function() {
            $('#scheduleDataTable').DataTable({
                "pageLength": 10,
                "ordering": true,
                "searching": true,
                "info": true,
                "order": [[4, 'asc']] // Sort by day
            });
        }, 100);
    }

    // Allow search on Enter key
    $('#searchInput').on('keypress', function(e) {
        if(e.which === 13) {
            $('#searchBtn').click();
        }
    });
});