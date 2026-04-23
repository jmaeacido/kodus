<!DOCTYPE html>
<html>
<body>
	<script>
		var isOriginal = true;

		function sidenavToggle() {
			var sideNav = document.getElementById("sidenav");
			var header = document.getElementById("header");
			var content = document.getElementById("content");
			var navBtn = document.getElementById("navBtn");
			var logo = document.getElementById("logo");
			var labels = document.querySelectorAll('.label');
			var icons = document.querySelectorAll('.sidenav a i');
			var buttons = document.querySelectorAll('.sidenav button i');
			var text = document.getElementById("text");

			if (!sideNav || !header) {
				console.error("SideNav, header, or navBtn element not found");
				return;
			}

			var computedWidth = window.getComputedStyle(sideNav).width;
			var isOpen = computedWidth === "150px";

			if (isOpen) {
				sideNav.style.width = "75px";
				header.style.marginLeft = "75px";
				content.style.marginLeft = "75px";
				logo.style.left = "8px";
				navBtn.style.transform = "rotate(-360deg)";
				// Hide labels with transition
				labels.forEach(label => {
					label.style.transitionDuration = "0.5s";
					label.style.display = "none";
					label.style.pointerEvents = "none"; // Optional to disable pointer events on hidden labels
				});
				// Adjust icon padding-left
				icons.forEach(icon => {
					icon.style.transitionDuration = "0.5s";
					icon.style.paddingLeft = "11px";
				});
				// Adjust button padding-left
				buttons.forEach(button => {
					button.style.transitionDuration = "0.5s";
					button.style.paddingLeft = "11px";
				});
			} else {
				sideNav.style.width = "150px";
				header.style.marginLeft = "150px";
				content.style.marginLeft = "150px";
				logo.style.left = "45px";
				navBtn.style.transform = "rotate(360deg)";
				// Show labels with transition
				labels.forEach(label => {
					label.style.transitionDuration = "0.5s";
					label.style.display = "inline";
					label.style.pointerEvents = "auto"; // Optional to enable pointer events on visible labels
				});
				// Reset icon padding-left
				icons.forEach(icon => {
					icon.style.paddingLeft = "0"; // Adjust back to the original padding
				});
				// Reset button padding-left
				buttons.forEach(button => {
					button.style.paddingLeft = "0"; // Adjust back to the original padding
				});
			}

			// Toggle between "KliMalasakit Online Document Updating System" and "KODUS" with smooth transition
			text.classList.add("text-hidden");
			setTimeout(function() {
				if (isOriginal) {
					text.textContent = "KODUS";
					isOriginal = false;
				} else {
					text.textContent = "KliMalasakit Online Document Updating System";
					isOriginal = true;
				}
				text.classList.remove("text-hidden");
			}, 500); // 500ms delay to match the transition duration
		}

		/* Updated event listener to toggle caret icon */
		var dropdown = document.getElementsByClassName("dropdown-btn");
		var i;

		for (i = 0; i < dropdown.length; i++) {
			dropdown[i].addEventListener("click", function() {
				this.classList.toggle("active");
				var caretIcon = this.querySelector("#caret");
				caretIcon.classList.toggle("fa-caret-down");
				caretIcon.classList.toggle("fa-caret-right");
				var dropdownContent = this.nextElementSibling;
				if (dropdownContent.style.display === "block") {
					dropdownContent.style.display = "none";
				} else {
				dropdownContent.style.display = "block";
				}
			});
		}
	</script>
    <script>
        $(document).ready(function () {
            $(document).on('click', '.editable-cell', function () {
                // Get the current content and date value
                var currentContent = $(this).text();
                var currentDate = $(this).data('date');

                // Replace the content with an input field of type "text"
                $(this).html('<input type="text" class="date-input" value="' + currentDate + '" placeholder="mm-dd-yyyy" tabindex="1">');

                // Focus on the input field
                $('.date-input').focus();

                // Handle input event on the input field
                $('.date-input').on('input', function () {
                    // Get the input value
                    var inputDate = $(this).val();

                    // Format the date with hyphens
                    var formattedDate = formatInputDate(inputDate);

                    // Update the input value with the formatted date
                    $(this).val(formattedDate);
                });

                // Handle blur event on the input field
                $('.date-input').on('blur', function () {
                    // Get the new date value
                    var newDate = $(this).val();

                    // Update the cell content with the formatted date
                    $(this).closest('.editable-cell').data('date', newDate);
                    $(this).closest('.editable-cell').text(newDate === "" ? "-" : formatDate(newDate));
                });
            });

            // Function to format date as "m-d-Y"
            function formatDate(date) {
                var formattedDate = new Date(date);
                var day = formattedDate.getDate();
                var month = formattedDate.getMonth() + 1;
                var year = formattedDate.getFullYear();
                return (month < 10 ? '0' : '') + month + '-' + (day < 10 ? '0' : '') + day + '-' + year;
            }

            // Function to format input date by adding hyphens
            function formatInputDate(inputDate) {
                // Remove non-numeric characters
                var numericInput = inputDate.replace(/[^\d]/g, '');

                // Insert hyphen after the month (mm)
                if (numericInput.length >= 2) {
                    numericInput = numericInput.slice(0, 2) + '-' + numericInput.slice(2);
                }

                // Insert hyphen after the day (dd)
                if (numericInput.length >= 5) {
                    numericInput = numericInput.slice(0, 5) + '-' + numericInput.slice(5);
                }

                return numericInput;
            }
        });
    </script>

    <script>
    function toggleChangeUser() {
    	console.log("Change Username Form is shown.")
        var changeUserBtn = document.getElementById("changeUserBtn");
        var changeUser = document.getElementById("changeUser");

        // Toggle the display of the changeUser div
        changeUser.style.display = (changeUser.style.display === "block") ? "none" : "block";
        changeUserBtn.style.display = (changeUser.style.display === "none") ? "block" : "none";
    }

    function cancelChangeUser() {
    	console.log("Change Username Form is hidden.")
        var changeUserBtn = document.getElementById("changeUserBtn");
        var changeUser = document.getElementById("changeUser");

        // Hide the changeUser div and show the changeUserBtn
        changeUser.style.display = "none";
        changeUserBtn.style.display = "block";
    }
	 
    function toggleChangePass() {
    	console.log("Change Password Form is shown.")
        var changePassBtn = document.getElementById("changePassBtn");
        var changePass = document.getElementById("changePass");

        // Toggle the display of the changePass div
        changePass.style.display = (changePass.style.display === "block") ? "none" : "block";
        changePassBtn.style.display = (changePass.style.display === "none") ? "block" : "none";
    }

    function cancelChangePass() {
    	console.log("Change Password Form is hidden.")
        var changePassBtn = document.getElementById("changePassBtn");
        var changePass = document.getElementById("changePass");

        // Hide the changePass div and show the changePassBtn
        changePass.style.display = "none";
        changePassBtn.style.display = "block";
    }
	    
    function toggleChangeDP() {
    	console.log("Change Display Photo Form is shown.")
        var changeDPBtn = document.getElementById("changeDPBtn");
        var changeDP = document.getElementById("changeDP");

        // Toggle the display of the changePass div
        changeDP.style.display = (changeDP.style.display === "block") ? "none" : "block";
        changeDPBtn.style.display = (changeDP.style.display === "none") ? "block" : "none";
    }

    function cancelChangeDP() {
    	console.log("Change Display Photo Form is hidden.")
        var changeDPBtn = document.getElementById("changeDPBtn");
        var changeDP = document.getElementById("changeDP");

        // Hide the changePass div and show the changePassBtn
        changeDP.style.display = "none";
        changeDPBtn.style.display = "block";
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('img2').style.display = 'block';
        document.getElementById('new_picture').value = '';
    }
    </script>
</body>
</html>