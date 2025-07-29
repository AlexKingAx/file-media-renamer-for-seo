jQuery(document).ready(function ($) {
  let renameProcessStarted = false; // Tracks if the rename operation was triggered
  const delay = fmrseoBulkRenameIds.length * 150;

  // Display the modal only if there are IDs to rename
  if (
    typeof fmrseoBulkRenameIds !== "undefined" &&
    Array.isArray(fmrseoBulkRenameIds) &&
    fmrseoBulkRenameIds.length > 0
  ) {
    $("#fmrseo-bulk-rename-modal").show();
    $("#fmrseo-bulk-name").focus().select();
  }

  // Handle modal close actions: clicking the close icon or a custom reload button
  $(
    ".fmrseo-close, .fmrseo-reload-button, #fmrseo-cancel-bulk, #fmrseo-close-bulk"
  ).on("click", handleModalClose);

  // Close modal when clicking outside of it (only reloads if a rename was performed)
  $(window).on("click", function (event) {
    if (event.target.id === "fmrseo-bulk-rename-modal") {
      handleModalClose();
    }
  });

  // Allow submission by pressing Enter inside the input field
  $("#fmrseo-bulk-name").on("keydown", function (e) {
    if (e.key === "Enter") {
      $("#fmrseo-start-bulk").click();
    }
  });

  // Handle click on "Start Bulk Rename" button
  $("#fmrseo-start-bulk").on("click", function () {
    const baseName = $("#fmrseo-bulk-name").val().trim();

    // Ensure base name is not empty
    if (!baseName) {
      alert("Please enter a base name for the files.");
      $("#fmrseo-bulk-name").focus();
      return;
    }

    // Confirm the bulk rename action with the user
    if (
      !confirm(
        `Are you sure you want to rename ${fmrseoBulkRenameIds.length} files?`
      )
    ) {
      return;
    }

    renameProcessStarted = true; // Mark that the rename process has been initiated

    // Disable the start button and show the progress interface
    $(this).prop("disabled", true);
    $("#fmrseo-start-bulk").hide();
    $("#fmrseo-cancel-bulk").hide();
    $("#fmrseo-close-bulk").show();
    $(".fmrseo-progress").show();
    $(".fmrseo-results").empty().show();

    processBulkRename(fmrseoBulkRenameIds, baseName);
  });

  // Perform AJAX request to server for bulk renaming
  function processBulkRename(postIds, baseName) {
    $.post(fmrseoBulkRename.ajax_url, {
      action: "fmrseo_bulk_rename",
      post_ids: postIds,
      base_name: baseName,
      nonce: fmrseoBulkRename.nonce,
    })
      .done(function (response) {
        if (response.success) {
          // Simulate progress bar based on estimated rename time
          simulateProgress(delay, function () {
            // When time completes, enable close button
            $("#fmrseo-close-bulk").prop("disabled", false);
          });

          // Fallback: force enable close button after max 10s
          setTimeout(() => {
            $("#fmrseo-close-bulk").prop("disabled", false);
          }, 10000);

          // Show results after delay (synchronized with progress)
          setTimeout(() => {
            displayResults(response.data);
            $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.completed);
          }, delay);
        } else {
          displayError(response.data.message); // Show error message from server
        }
      })
      .fail(function () {
        displayError(fmrseoBulkRename.strings.error); // Generic error on request failure
      })
      .always(function () {
        // Fallback: force enable close button after max 15s
        setTimeout(() => {
          $("#fmrseo-close-bulk").prop("disabled", false);
        }, 15000);
      });
  }

  // Update the progress bar and its label
  function updateProgress(percentage) {
    $(".fmrseo-progress-fill").css("width", percentage + "%");
    $(".fmrseo-progress-text").text(percentage + "%");
  }

  // Display rename results in a readable format
  function displayResults(results) {
    const html = ["<h4>Results:</h4><ul>"];
    results.forEach(({ success, old_name, new_name, post_id, message }) => {
      const statusClass = success ? "success" : "error";
      const statusIcon = success ? "✓" : "✗";
      const resultText = success
        ? `<strong>${old_name}</strong> → <strong>${new_name}</strong>`
        : `ID: ${post_id} - ${message}`;

      html.push(
        `<li class=\"fmrseo-result-${statusClass}\"><span class=\"fmrseo-status-icon\">${statusIcon}</span> ${resultText}</li>`
      );
    });
    html.push("</ul>");
    $(".fmrseo-results").html(html.join(""));
  }

  // Show a general or specific error message in the result section
  function displayError(message) {
    $(".fmrseo-results").html(
      `<div class=\"fmrseo-error\">Error: ${message}</div>`
    );
    updateProgress(0);
  }

  /**
   * Handles the logic for closing the modal dialog.
   * If the bulk rename process was initiated, this function will:
   *   - Clean the current URL by removing any temporary query parameters used to trigger the modal
   *   - Append a timestamp parameter to bypass browser cache
   *   - Reload the page with the cleaned URL
   * This reset ensures the modal is not shown again on page reload and that fresh content is loaded.
   */
  function handleModalClose() {
    $("#fmrseo-bulk-rename-modal").hide();

    if (renameProcessStarted) {
      let url = window.location.href
        .replace(/([?&])fmrseo_bulk_rename=1(&)?/, (match, p1, p2) =>
          p2 ? p1 : ""
        )
        .replace(/([?&])fmrseo_force_reload=\d+(&)?/, (match, p1, p2) =>
          p2 ? p1 : ""
        )
        .replace(/[?&]$/, "");

      const separator = url.includes("?") ? "&" : "?";
      const reloadUrl = url + separator + "fmrseo_force_reload=" + Date.now();
      window.location.href = reloadUrl;
    }
  }
  /**
   * Simulates a progress bar over a specified duration.
   * @param {number} duration - Duration in milliseconds for the progress simulation.
   * @param {function} onComplete - Callback function to execute when progress completes.
   */
  function simulateProgress(duration, onComplete) {
    const start = Date.now();
    const interval = 100; // update every 100ms

    const timer = setInterval(() => {
      const elapsed = Date.now() - start;
      let percent = Math.min((elapsed / duration) * 100, 100);
      updateProgress(Math.floor(percent));

      if (percent >= 100) {
        clearInterval(timer);
        if (typeof onComplete === "function") onComplete();
      }
    }, interval);
  }
});
