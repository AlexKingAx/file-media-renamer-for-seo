jQuery(document).ready(function ($) {
  let renameProcessStarted = false; // Tracks if the rename operation was triggered
  let currentRenameMethod = "manual";
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

  // Handle rename method selection
  $('input[name="fmrseo-rename-method"]').on("change", function () {
    const method = $(this).val();
    if (method === "ai") {
      $("#fmrseo-manual-options").hide();
      $("#fmrseo-ai-options").show();
    } else {
      $("#fmrseo-manual-options").show();
      $("#fmrseo-ai-options").hide();
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
    const renameMethod = $('input[name="fmrseo-rename-method"]:checked').val();
    const baseName = $("#fmrseo-bulk-name").val().trim();

    // Validate input based on method
    if (renameMethod === "manual") {
      if (!baseName) {
        alert("Please enter a base name for the files.");
        $("#fmrseo-bulk-name").focus();
        return;
      }
    }

    // Confirm the bulk rename action with the user
    const methodText = renameMethod === "ai" ? "AI rename" : "rename";
    const confirmMessage = renameMethod === "ai" 
      ? `Are you sure you want to AI rename ${fmrseoBulkRenameIds.length} files? This will use ${fmrseoBulkRenameIds.length} credits.`
      : `Are you sure you want to rename ${fmrseoBulkRenameIds.length} files?`;
    
    if (!confirm(confirmMessage)) {
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

    currentRenameMethod = renameMethod;
    processBulkRename(fmrseoBulkRenameIds, baseName, renameMethod);
  });

  // Perform AJAX request to server for bulk renaming
  function processBulkRename(postIds, baseName, renameMethod = "manual") {
    if (renameMethod === "ai") {
      // Use progressive processing for AI
      processAIBulkRename(postIds);
    } else {
      // Use traditional bulk processing for manual rename
      const requestData = {
        action: "fmrseo_bulk_rename",
        post_ids: postIds,
        rename_method: renameMethod,
        base_name: baseName,
        nonce: fmrseoBulkRename.nonce,
      };

      $.post(fmrseoBulkRename.ajax_url, requestData)
        .done(function (response) {
          if (response.success) {
            // Calculate delay based on method - AI uses progressive processing
            const progressDelay = delay; // Only for manual method

            // Simulate progress bar based on estimated rename time
            simulateProgress(progressDelay, function () {
              // When time completes, enable close button
              $("#fmrseo-close-bulk").prop("disabled", false);
            });

            // Fallback: force enable close button after max time
            const maxTimeout = 10000; // 10s for manual
            setTimeout(() => {
              $("#fmrseo-close-bulk").prop("disabled", false);
            }, maxTimeout);

            // Show results after delay (synchronized with progress)
            setTimeout(() => {
              displayResults(response.data);
              $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.completed);
            }, progressDelay);
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
  }

  // Update the progress bar and its label
  function updateProgress(percentage) {
    $(".fmrseo-progress-fill").css("width", percentage + "%");
    $(".fmrseo-progress-text").text(percentage + "%");
  }

  // Display rename results in a readable format
  function displayResults(results) {
    const html = ["<h4>Results:</h4><ul>"];
    let successCount = 0;
    let failCount = 0;
    let creditsUsed = 0;
    let summary = null;
    
    // Check if results has summary information
    if (results._summary) {
      summary = results._summary;
      // Remove summary from results array for processing
      delete results._summary;
    }
    
    results.forEach(({ success, old_name, new_name, post_id, message, method, credits_used }) => {
      const statusClass = success ? "success" : "error";
      const statusIcon = success ? "✓" : "✗";
      const methodBadge = method === "ai" ? '<span class="fmrseo-ai-badge">AI</span>' : '';
      
      let resultText;
      if (success) {
        resultText = `${methodBadge}<strong>${old_name}</strong> → <strong>${new_name}</strong>`;
        successCount++;
        if (credits_used) {
          creditsUsed += credits_used;
        }
      } else {
        resultText = `${methodBadge}<strong>${old_name || 'ID: ' + post_id}</strong> - ${message}`;
        failCount++;
      }

      html.push(
        `<li class=\"fmrseo-result-${statusClass}\"><span class=\"fmrseo-status-icon\">${statusIcon}</span> ${resultText}</li>`
      );
    });
    
    html.push("</ul>");
    
    // Add summary - use server summary if available, otherwise calculate from results
    if (currentRenameMethod === "ai") {
      const finalSuccessCount = summary ? summary.successful : successCount;
      const finalFailCount = summary ? summary.failed : failCount;
      const finalCreditsUsed = summary ? summary.credits_used : creditsUsed;
      
      html.push(`<div class="fmrseo-ai-summary">`);
      html.push(`<p><strong>Summary:</strong> ${finalSuccessCount} files renamed successfully, ${finalFailCount} failed.</p>`);
      if (finalCreditsUsed > 0) {
        html.push(`<p><strong>Credits used:</strong> ${finalCreditsUsed}</p>`);
      }
      if (summary && summary.total_processed) {
        html.push(`<p><strong>Total processed:</strong> ${summary.total_processed}</p>`);
      }
      html.push(`</div>`);
    }
    
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
   * Process AI bulk rename with individual file handling and real progress tracking
   */
  function processAIBulkRename(postIds) {
    let processedCount = 0;
    let successCount = 0;
    let failCount = 0;
    let creditsUsed = 0;
    const results = [];
    const totalFiles = postIds.length;

    // Update progress text for AI processing
    $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.ai_processing || "AI is analyzing files...");

    // Process each file individually
    function processNextFile(index) {
      if (index >= totalFiles) {
        // All files processed - show final results
        displayResults(results);
        $(".fmrseo-progress-text").text(fmrseoBulkRename.strings.completed);
        $("#fmrseo-close-bulk").prop("disabled", false);
        return;
      }

      const postId = postIds[index];
      
      $.post(fmrseoBulkRename.ajax_url, {
        action: "fmrseo_bulk_ai_rename_progressive",
        post_id: postId,
        batch_index: index,
        total_files: totalFiles,
        nonce: fmrseoBulkRename.nonce,
      })
      .done(function (response) {
        processedCount++;
        
        if (response.success) {
          const data = response.data;
          successCount++;
          if (data.credits_used) {
            creditsUsed += data.credits_used;
          }
          
          results.push({
            success: true,
            post_id: data.post_id,
            old_name: data.old_name,
            new_name: data.new_name,
            message: data.message,
            method: data.method,
            credits_used: data.credits_used
          });
        } else {
          failCount++;
          results.push({
            success: false,
            post_id: response.data ? response.data.post_id : postId,
            old_name: response.data ? response.data.old_name : `ID: ${postId}`,
            message: response.data ? response.data.message : "Unknown error",
            method: "ai"
          });
        }

        // Update progress
        const progress = Math.round((processedCount / totalFiles) * 100);
        updateProgress(progress);
        
        // Update progress text with current status
        $(".fmrseo-progress-text").text(`${progress}% (${processedCount}/${totalFiles})`);

        // Process next file
        setTimeout(() => processNextFile(index + 1), 100); // Small delay between requests
      })
      .fail(function () {
        processedCount++;
        failCount++;
        results.push({
          success: false,
          post_id: postId,
          old_name: `ID: ${postId}`,
          message: "Network error or server timeout",
          method: "ai"
        });

        // Update progress even on failure
        const progress = Math.round((processedCount / totalFiles) * 100);
        updateProgress(progress);
        $(".fmrseo-progress-text").text(`${progress}% (${processedCount}/${totalFiles})`);

        // Continue with next file even if this one failed
        setTimeout(() => processNextFile(index + 1), 100);
      });
    }

    // Start processing from first file
    processNextFile(0);
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
