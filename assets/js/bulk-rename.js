jQuery(document).ready(function ($) {

  // ─── STATE FLAGS ────────────────────────────────────────────────────────────

  let renameProcessStarted = false; // True once the user has launched any rename operation
  let isProcessing         = false; // True while an AJAX operation is running
  let isCancelled          = false; // True if the user pressed "Cancel" during AI processing
  let aiResults            = [];    // Accumulates results from each AI batch step

  // ─── CONFIGURATION ──────────────────────────────────────────────────────────

  // Sanitize and validate the list of post IDs passed from PHP
  const ids = (
    typeof fmrseoBulkRenameIds !== "undefined" && Array.isArray(fmrseoBulkRenameIds)
      ? fmrseoBulkRenameIds.map((id) => Number(id)).filter((id) => id > 0)
      : []
  );

  const totalItems   = ids.length;

  // AI-specific settings passed from PHP (with safe fallbacks)
  const aiDelayMs    = Math.max(0, Number(fmrseoBulkRename.ai_delay_ms  || 2000)); // ms to wait between AI batches
  const aiBatchSize  = Math.max(1, Math.min(5, Number(fmrseoBulkRename.ai_batch_size || 1))); // items per AI batch (1–5)
  const aiMaxFiles   = Math.max(0, Number(fmrseoBulkRename.ai_max_files || 0)); // 0 = no limit

  // ─── INIT: SHOW MODAL ───────────────────────────────────────────────────────

  // Only open the modal if there is at least one file to rename
  if (totalItems > 0) {
    $("#fmrseo-bulk-rename-modal").show();
    $("#fmrseo-bulk-name").focus().select();
  }

  // ─── EVENT LISTENERS ────────────────────────────────────────────────────────

  // Close button, reload button → close the modal (and reload the page if needed)
  $(".fmrseo-close, .fmrseo-reload-button, #fmrseo-close-bulk").on("click", handleModalClose);

  // Cancel button:
  //   • If a process is running  → set the cancellation flag (the loop will stop at the next batch)
  //   • If nothing is running    → just close the modal
  $("#fmrseo-cancel-bulk").on("click", function () {
    if (isProcessing) {
      isCancelled = true;
      $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.cancelling);
      return;
    }
    handleModalClose();
  });

  // Clicking outside the modal closes it, but NOT while a process is running
  $(window).on("click", function (event) {
    if (event.target.id === "fmrseo-bulk-rename-modal" && !isProcessing) {
      handleModalClose();
    }
  });

  // Allow the user to submit the form by pressing Enter inside the name input
  $("#fmrseo-bulk-name").on("keydown", function (e) {
    if (e.key === "Enter") {
      $("#fmrseo-start-bulk").click();
    }
  });

  // ─── MANUAL RENAME ──────────────────────────────────────────────────────────

  // User clicks "Start Bulk Rename" → rename all files using the typed base name
  $("#fmrseo-start-bulk").on("click", function () {
    const baseName = $("#fmrseo-bulk-name").val().trim();

    // The base name must not be empty
    if (!baseName) {
      alert(fmrseoBulkRename.strings.missing_base_name);
      $("#fmrseo-bulk-name").focus();
      return;
    }

    // Ask for confirmation before doing anything irreversible
    const confirmText = formatString(fmrseoBulkRename.strings.confirm_manual, totalItems);
    if (!confirm(confirmText)) {
      return;
    }

    startManualBulkRename(baseName);
  });

  // Sends all IDs + the base name to the server in a single AJAX call
  function startManualBulkRename(baseName) {
    prepareProcessUi();

    $.post(fmrseoBulkRename.ajax_url, {
      action:    "fmrseo_bulk_rename",
      post_ids:  ids,
      base_name: baseName,
      nonce:     fmrseoBulkRename.nonce,
    })
      .done(function (response) {
        if (!response.success) {
          displayError(response.data.message || fmrseoBulkRename.strings.error);
          finishProcess();
          return;
        }

        // Show every renamed file and mark the progress bar as complete
        displayResults(response.data || []);
        updateProgress(100);
        $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.completed);
        finishProcess();
      })
      .fail(function () {
        displayError(fmrseoBulkRename.strings.error);
        finishProcess();
      });
  }

  // ─── AI RENAME ──────────────────────────────────────────────────────────────

  // User clicks "Start AI Rename" → rename files one batch at a time using AI
  $("#fmrseo-start-bulk-ai").on("click", function () {

    // AI must be enabled and an API key must be configured on the server
    if (!fmrseoBulkRename.ai_enabled || !fmrseoBulkRename.ai_key_set) {
      alert(fmrseoBulkRename.strings.ai_unavailable);
      return;
    }

    // Respect the maximum file limit for AI (if one is set)
    if (aiMaxFiles > 0 && totalItems > aiMaxFiles) {
      alert(fmrseoBulkRename.strings.ai_limit_reached);
      return;
    }

    // Ask for confirmation before starting (AI calls may cost money or take time)
    const confirmText = formatString(fmrseoBulkRename.strings.confirm_ai, totalItems);
    if (!confirm(confirmText)) {
      return;
    }

    // Reset accumulated results and start from offset 0
    aiResults = [];
    prepareProcessUi();
    processAIBatch(0);
  });

  /**
   * Processes one batch of files via AI, then schedules the next batch.
   * The server handles one batch at a time and returns the next offset,
   * so this function calls itself recursively until all files are processed
   * or the user cancels.
   *
   * @param {number} offset - Index of the first file to process in this batch
   */
  function processAIBatch(offset) {

    // Stop immediately if the user pressed "Cancel"
    if (isCancelled) {
      finishCancelled();
      return;
    }

    $.post(fmrseoBulkRename.ajax_url, {
      action:     "fmrseo_bulk_ai_rename_step",
      post_ids:   ids,
      offset:     offset,
      batch_size: aiBatchSize,
      nonce:      fmrseoBulkRename.ai_nonce,
    })
      .done(function (response) {
        if (!response.success) {
          displayError(response.data.message || fmrseoBulkRename.strings.error);
          finishProcess();
          return;
        }

        const data        = response.data || {};
        const stepResults = Array.isArray(data.results) ? data.results : [];
        const nextOffset  = Number(data.next_offset || 0);
        const done        = Boolean(data.done);

        // Append this batch's results to the full list and refresh the display
        if (stepResults.length > 0) {
          aiResults = aiResults.concat(stepResults);
          displayResults(aiResults);
        }

        // Update the progress bar based on how many files have been processed
        const percentage = totalItems > 0
          ? Math.min(Math.round((nextOffset / totalItems) * 100), 100)
          : 100;
        updateProgress(percentage);

        // If the server says we're done (or we've passed all IDs), stop here
        if (done || nextOffset >= totalItems) {
          $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.completed);
          finishProcess();
          return;
        }

        // Wait the configured delay before sending the next batch
        // (avoids hammering the AI API and gives the UI time to breathe)
        setTimeout(function () {
          processAIBatch(nextOffset);
        }, aiDelayMs);
      })
      .fail(function () {
        displayError(fmrseoBulkRename.strings.error);
        finishProcess();
      });
  }

  // ─── UI HELPERS ─────────────────────────────────────────────────────────────

  /**
   * Prepares the modal UI before any rename operation starts:
   * shows the progress bar, disables inputs, resets state flags.
   */
  function prepareProcessUi() {
    renameProcessStarted = true;
    isProcessing         = true;
    isCancelled          = false;

    $(".fmrseo-progress").show();
    $(".fmrseo-results").empty().show();

    // Close button is shown but kept disabled until the process ends
    $("#fmrseo-close-bulk").show().prop("disabled", true);

    // Lock all inputs while the operation is running
    $("#fmrseo-start-bulk").prop("disabled", true);
    $("#fmrseo-start-bulk-ai").prop("disabled", true);
    $("#fmrseo-bulk-name").prop("disabled", true);

    $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.processing);
    updateProgress(0);
  }

  // Called when a process finishes successfully: unlocks the Close button
  function finishProcess() {
    isProcessing = false;
    $("#fmrseo-close-bulk").prop("disabled", false);
  }

  // Called when the user cancels mid-process: shows a cancellation message
  function finishCancelled() {
    displayError(fmrseoBulkRename.strings.cancelled);
    isProcessing = false;
    $("#fmrseo-close-bulk").prop("disabled", false);
  }

  // Moves the progress bar to the given percentage (0–100)
  function updateProgress(percentage) {
    $(".fmrseo-progress-fill").css("width", percentage + "%");

    // Only overwrite the label while processing
    // (after completion the caller sets its own label, e.g. "Completed")
    if (isProcessing) {
      $(".fmrseo-progress-text").text(percentage + "%");
    }
  }

  /**
   * Renders the list of rename results inside the modal.
   * Each item shows a green checkmark on success or a red cross on failure.
   *
   * @param {Array} results - Array of result objects from the server
   */
  function displayResults(results) {
    const html = ["<h4>" + fmrseoBulkRename.strings.results + "</h4><ul>"];

    results.forEach(function (result) {
      const success     = Boolean(result.success);
      const statusClass = success ? "success" : "error";
      const statusIcon  = success ? "&#10003;" : "&#10007;"; // ✓ or ✗

      // Success → show "old name → new name"
      // Failure → show the post ID and the error message
      const resultText = success
        ? "<strong>" + escapeHtml(result.old_name) + "</strong> &rarr; <strong>" + escapeHtml(result.new_name) + "</strong>"
        : "ID: " + escapeHtml(result.post_id) + " - " + escapeHtml(result.message || "");

      html.push(
        '<li class="fmrseo-result-' + statusClass + '">' +
          '<span class="fmrseo-status-icon">' + statusIcon + '</span> ' +
          resultText +
        '</li>'
      );
    });

    html.push("</ul>");
    $(".fmrseo-results").html(html.join(""));
  }

  // Shows a red error message in the results area and resets the progress bar
  function displayError(message) {
    $(".fmrseo-results").html(
      '<div class="fmrseo-error">' +
        fmrseoBulkRename.strings.error_prefix + " " +
        escapeHtml(message || fmrseoBulkRename.strings.error) +
      "</div>"
    );
  }

  // ─── MODAL CLOSE & PAGE RELOAD ──────────────────────────────────────────────

  /**
   * Handles closing the modal dialog.
   *
   * If a rename operation was performed, we cannot just close the modal:
   * the media library page still shows the old file names. So we clean the
   * URL (removing temporary query parameters added to trigger this modal)
   * and force a full page reload so the library reflects the new names.
   *
   * If no rename was started, we just hide the modal without reloading.
   */
  function handleModalClose() {

    // Never close while an AJAX operation is still in flight
    if (isProcessing) {
      return;
    }

    $("#fmrseo-bulk-rename-modal").hide();

    if (renameProcessStarted) {

      // Strip the temporary parameters added by the plugin to open this modal
      let url = window.location.href
        .replace(/([?&])fmrseo_bulk_rename=1(&)?/, function (match, p1, p2) {
          return p2 ? p1 : "";
        })
        .replace(/([?&])fmrseo_bulk_rename_nonce=[^&]*(&)?/, function (match, p1, p2) {
          return p2 ? p1 : "";
        })
        .replace(/([?&])fmrseo_force_reload=\d+(&)?/, function (match, p1, p2) {
          return p2 ? p1 : "";
        })
        .replace(/[?&]$/, ""); // Remove any trailing ? or & left behind

      // Append a unique timestamp to bypass the browser cache and force a fresh load
      const separator = url.includes("?") ? "&" : "?";
      window.location.href = url + separator + "fmrseo_force_reload=" + Date.now();
    }
  }

  // ─── UTILITY FUNCTIONS ──────────────────────────────────────────────────────

  /**
   * Replaces the first "%d" placeholder in a template string with a number.
   * Used to build confirmation messages like "Are you sure? 5 files will be renamed."
   *
   * @param {string} template - String containing "%d"
   * @param {number} count    - Number to insert
   * @returns {string}
   */
  function formatString(template, count) {
    return String(template || "").replace("%d", String(count));
  }

  /**
   * Escapes a value so it is safe to inject into HTML.
   * Prevents XSS when displaying file names returned by the server.
   *
   * @param {*} value - Any value (will be converted to string)
   * @returns {string} HTML-escaped string
   */
  function escapeHtml(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
  }

});