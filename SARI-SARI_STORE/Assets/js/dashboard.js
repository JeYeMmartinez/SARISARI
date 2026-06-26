const baseUrl = window.location.origin;

$(document).ready(function () {
  const isMobile = window.matchMedia("(max-width: 768px)").matches;
  $("#campusMap [data-type]").hide();  //Related Toggle Icon Part: Naka hide  
  
  const $svg = $("#campusMap");
  const $buildings = $svg.find(".building");
  let $selected = null;
  let isPanning = false;

  $(document).on("click", ".building", function (e) {
    e.preventDefault();

    if (isPanning) return; 

    const buildingId = $(this).data("building-number");

    
    $(".building").removeClass("selected");
    $(this).addClass("selected");

    if (isMobile) {
      $(this).addClass("tapped"); 
      setTimeout(() => $(this).removeClass("tapped"), 500); 
    }

    loadBuildingDetails(buildingId);
  });

  // ===============================
  // Dropdown building & Selection indication (Hinalo ko na dito) NOT WORKING UNG DROP DOWN YET
  // ===============================

  $(document).on("click", ".dropdown_buildings", function(e) {
    e.preventDefault();

    const buildingNumber = $(this).data("building-number");

    const $targetBuilding = $(svgDoc).find(`.building[data-building-number="${buildingNumber}"]`);

    if ($targetBuilding.length) {
      $(svgDoc).find('.building.selected').removeClass('selected');
      $targetBuilding.addClass('selected');
      $selected = $targetBuilding;
      loadBuildingDetails(buildingNumber);
      $('#sidepanel')[0].scrollIntoView({ behavior: 'smooth' });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No matched building found for number: ' + buildingNumber,
      });
    }
  });

  // ===============================
  // Building data dito
  // ===============================
  function loadBuildingDetails(buildingId) {
    $("#default-state").hide();

    $.ajax({
      url: baseUrl + "/CAMPUS_NAVIGATION_SYSTEM/config/BUILDING.php",
      method: "POST",
      data: { building_number: buildingId },
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          let building = response.data.building;
          $("#sidepanel")[0].scrollIntoView({ behavior: "smooth" });

          $("#display_building_name")
            .text(building.name)
            .css({ "font-size": "25px", "font-family": "Poppins" });

          $("#display_building_description")
            .text("PICK A FLOOR :")
            .css({ "text-align": "center" });

          $("#display_building_floors").empty();
          $("#display_building_rooms").empty();

          $.each(response.data.floors, function (i, floor) {
            let floorBtn = $("<button>")
              .addClass("floor-btn btn btn-primary p-2")
              .text("Floor " + floor.floor_number)
              .css("cursor", "pointer")
              .on("click", function () {
                $("#display_building_rooms").empty();
                $.each(floor.rooms, function (j, room) {
                  $("#display_building_rooms").append(
                    `<button class="room-btn btn btn-outline-primary w-100 my-1" 
                      data-room="${room.room_id}" 
                      data-room-type="${room.type}">
                      Room ${room.room_id} (${room.type})
                    </button>`
                  );
                });
              });

            $("#display_building_floors").append(floorBtn);
          });
        } else {
          Swal.fire("Error", response.message, "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error: ", error);
        Swal.fire("Error", "Something went wrong!", "error");
      },
    });
  }

  // ===============================
  // Room offcanvas
  // ===============================
  $(document).on("click", ".room-btn", function () {
    const roomId = $(this).data("room");
    const roomType = $(this).data("room-type");

    $("#roomTitle").text("Room " + roomId);
    $("#roomBody").html(`
      <p><strong>Room Number: </strong>${roomId}</p>
      <p><strong>Type:</strong> ${roomType}</p>
      <p class="text-center"> --------IMAGE DITO----------</p>
      <p><em>Hello sir</em></p>
    `);

    const roomOffcanvas = new bootstrap.Offcanvas(
      document.getElementById("roomOffcanvas")
    );
    roomOffcanvas.show();
  });

  // ===============================
  // Legend functions dito
  // ===============================

  $buildings.attr("tabindex", 0).on("keydown", function (e) {
    if (e.key === "Enter" || e.keyCode === 13) $(this).trigger("click");
  });

  function applyLegendColors() {

    const $gates = $('.gate');
    console.log(`🟡 Checking gates... Found ${$gates.length}`);

    if ($gates.length === 0) {

      return setTimeout(applyLegendColors, 300);
    }

    $gates.each(function() {
      const color = $(this).attr('fill');
      const type = $(this).data('type');
      console.log('🎨 Found gate:', type, color);

      const $legend = $(`.legend-color[data-type="${type}"]`);
      if ($legend.length) {
        $legend.css({
          'background-color': color,
          'border': '1px solid #000',
          'border-radius': '50%',
          'width': '15px',
          'height': '15px',
          'display': 'inline-block'
        });
      } 
    });
  }


  $(document).ready(function() {
    applyLegendColors();
  });

  // ===============================
  // Toggle show/hide icon dito
  // ===============================

  let activeTypes = {};

  $(".legend-link").on("click", function(e) {
    e.preventDefault(); 

    let type = $(this).siblings(".legend-color").data("type");

    activeTypes[type] = !activeTypes[type];

    $(this).css({
      "font-weight": activeTypes[type] ? "bold" : "normal", 
      "opacity": activeTypes[type] ? "1" : "0.6",
    });

    const $icons = $("#campusMap").find(`[data-type="${type}"]`);

    if (activeTypes[type]) {
      console.log("Toggled:", type, "Found:", $icons.length);
      $icons.stop(true, true).fadeIn(300).css("pointer-events", "auto");
    } else {
      $icons.stop(true, true).fadeOut(300).css("pointer-events", "none");
    }

  });

  // ===============================
  // Map zoom and pan dito (MOBILE)
  // ===============================

  if (isMobile) {
    let touchStart = { x: 0, y: 0 };
    $svg.on("touchstart", function (e) {
      e.preventDefault();
      mouseDown = true; 
      touchStart.x = e.touches[0].clientX;
      touchStart.y = e.touches[0].clientY;
      panStart.x = touchStart.x;
      panStart.y = touchStart.y;
    });

    $svg.on("touchmove", function (e) {
      if (!mouseDown) return;
      let movedPx = Math.hypot(e.touches[0].clientX - touchStart.x, e.touches[0].clientY - touchStart.y);
      if (!isPanning && movedPx > dragThreshold) {
        isPanning = true;
      }
      if (isPanning) {
        let dx = ((e.touches[0].clientX - panStart.x) / $svg.width()) * width;
        let dy = ((e.touches[0].clientY - panStart.y) / $svg.height()) * height;
        x -= dx;
        y -= dy;
        $svg.attr("viewBox", `${x} ${y} ${width} ${height}`);
        panStart.x = e.touches[0].clientX;
        panStart.y = e.touches[0].clientY;
      }
    });

    $svg.on("touchend", function () {
      mouseDown = false;
      isPanning = false;
    });

    $svg.on("gesturechange", function (e) {
      e.preventDefault();
      const scale = e.originalEvent.scale;
      if (scale > 1) {
        width /= scale; // Zoom in
        height /= scale;
      } else {
        width *= scale; // Zoom out
        height *= scale;
      }
      $svg.attr("viewBox", `${x} ${y} ${width} ${height}`);
    });
  }
  

  // ===============================
  // Map zoom and pan dito
  // ===============================
  let viewBox = $svg.attr("viewBox").split(" ").map(Number);
  let [x, y, width, height] = viewBox;
  let panStart = { x: 0, y: 0 };
  let mouseDown = false;
  let preventClick = false;
  let dragThreshold = 6;

  $svg.on("mousedown", function (e) {
    mouseDown = true;
    preventClick = false;
    panStart.x = e.clientX;
    panStart.y = e.clientY;
  });

  $(window).on("mouseup", function () {
    mouseDown = false;
    if (isPanning) {
      setTimeout(() => {
        isPanning = false;
        preventClick = false;
      }, 250);
    } else {
      preventClick = false;
    }
  });

  $svg.on("mousemove", function (e) {
    if (!mouseDown) return;
    let movedPx = Math.hypot(e.clientX - panStart.x, e.clientY - panStart.y);

    if (!isPanning && movedPx > dragThreshold) {
      isPanning = true;
      preventClick = true;
    }
    if (!isPanning) return;

    let dx = ((e.clientX - panStart.x) / $svg.width()) * width;
    let dy = ((e.clientY - panStart.y) / $svg.height()) * height;

    x -= dx;
    y -= dy;
    $svg.attr("viewBox", `${x} ${y} ${width} ${height}`);
    panStart.x = e.clientX;
    panStart.y = e.clientY;
  });

  $svg.on("wheel", function (e) {
    e.preventDefault();
    const scale = 1.1;
    const mouseX = (e.originalEvent.offsetX / $svg.width()) * width + x;
    const mouseY = (e.originalEvent.offsetY / $svg.height()) * height + y;

    if (e.originalEvent.deltaY < 0) {
      width /= scale;
      height /= scale;
    } else {
      width *= scale;
      height *= scale;
    }

    x = mouseX - (e.originalEvent.offsetX / $svg.width()) * width;
    y = mouseY - (e.originalEvent.offsetY / $svg.height()) * height;
    $svg.attr("viewBox", `${x} ${y} ${width} ${height}`);
  });

  // ===============================
  // SCHEDULE ALERT 
  // ===============================

  const svgObject = document.getElementById("campusMap");

  function initHighlightWatcher() {
    console.log("✅ Running schedule highlight watcher...");
    highlightCurrentBuilding();
    setInterval(highlightCurrentBuilding, 60000);
  }

  // Initialize watcher depending on SVG load state
  if (svgObject && svgObject instanceof SVGSVGElement) {
    initHighlightWatcher();
  } else if (svgObject && svgObject.contentDocument) {
    initHighlightWatcher();
  } else if (svgObject) {
    svgObject.addEventListener("load", initHighlightWatcher);
  } else {
    console.warn("⚠️ No SVG found with ID #campusMap");
  }

  let lastAlertedSubject = null;

  function highlightCurrentBuilding() {
    $.getJSON(baseUrl + "/CAMPUS_NAVIGATION_SYSTEM/config/SCHEDULE.php?today=true", function (data) {
      const now = new Date();
      const currentDay = now.toLocaleDateString("en-US", { weekday: "long" });

      // Find matching class based on actual time comparison
      const currentClass = data.find((item) => {
        const [sh, sm, ss] = item.time_start.split(":").map(Number);
        const [eh, em, es] = item.time_end.split(":").map(Number);

        const startTime = new Date(now);
        startTime.setHours(sh, sm, ss || 0, 0);

        const endTime = new Date(now);
        endTime.setHours(eh, em, es || 0, 0);

    
        if (endTime < startTime) {
          endTime.setDate(endTime.getDate() + 1);
        }

        const isMatch =
          item.day.trim().toLowerCase() === currentDay.toLowerCase() &&
          now >= startTime &&
          now <= endTime;

        
        console.log({
          subject: item.subject_name,
          day: item.day,
          start: startTime.toTimeString().slice(0, 8),
          end: endTime.toTimeString().slice(0, 8),
          now: now.toTimeString().slice(0, 8),
          match: isMatch,
        });

        return isMatch;
      });

      // Remove previous highlights
      $(".building").removeClass("active-schedule");

      if (currentClass) {
        const buildingName = currentClass.building_name.trim();
        const subject = currentClass.subject_name;
        const room = currentClass.room_name;
        const roomId = currentClass.room_id;
        const buildingNumber = currentClass.building_number;
        const $target = $(`.building[data-building-number='${buildingNumber}']`);

        // Only alert once per subject
        if (subject !== lastAlertedSubject) {
          lastAlertedSubject = subject;

          Swal.fire({
            icon: "info",
            title: "Class Reminder",
            html: `<b>${subject}</b> is starting now! <br> Location: <b>${buildingName}</b> - Room ${roomId}`,
            timer: 5000,
            timerProgressBar: true,
            showConfirmButton: false,
          });
        }

        // Highlight building on map
        if ($target.length) {
          $target.addClass("active-schedule");
          console.log("🏫 Highlighting Building:", buildingName);

          loadBuildingDetails(buildingNumber);

          // Highlight floor and room
          setTimeout(() => {
            const floorNumber = currentClass.floor_number;
            const roomName = currentClass.room_name;

            $("#display_building_floors button")
              .filter(function () {
                return $(this).text().includes(floorNumber);
              })
              .addClass("btn-warning");

            $("#display_building_rooms button")
              .filter(function () {
                return $(this).text().includes(roomName);
              })
              .addClass("btn-warning");
          }, 1000);
        }
      } else {
        lastAlertedSubject = null;
      }
    });
  }

  // ===============================
  //search function dito
  // ===============================

  const searchInput = document.getElementById('searchInput');
  const suggestionsBox = document.getElementById('suggestions');

  searchInput.addEventListener('input', async function() {
      const query = this.value.trim();

      // Clear suggestions if empty
      if (query.length === 0) {
          suggestionsBox.innerHTML = '';
          suggestionsBox.style.display = 'none';
          return;
      }

      try {
          const response = await fetch('search.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ query })
          });

          const data = await response.json();

          if (data.status === 'success' && data.results.length > 0) {
              suggestionsBox.innerHTML = data.results.map(item => `
                  <div class="suggestion-item" 
                      data-building="${item.building_number}" 
                      data-type="${item.result_type}">
                      ${item.display_name}
                  </div>
              `).join('');
              suggestionsBox.style.display = 'block';
          } else {
              suggestionsBox.innerHTML = '<div class="no-result">No matches found</div>';
              suggestionsBox.style.display = 'block';
          }

      } catch (err) {
          console.error('Search error:', err);
      }
  });


  suggestionsBox.addEventListener('click', function(e) {
      const item = e.target.closest('.suggestion-item');
      if (!item) return;

      const buildingNumber = item.dataset.building;
      const resultType = item.dataset.type;

      searchInput.value = item.textContent; 
      suggestionsBox.innerHTML = '';
      suggestionsBox.style.display = 'none';

     
      console.log('Clicked:', buildingNumber, resultType);
  });
});