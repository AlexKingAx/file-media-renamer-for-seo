jQuery(document).ready(function ($) {

  // ─── MANUAL RENAME (Save SEO Name button) ───────────────────────────────────

  /**
   * Handles the click on the "Save SEO Name" button inside the media modal.
   *
   * We use $(document).on() instead of a direct binding because the media modal
   * is injected dynamically into the DOM — the button doesn't exist on page load.
   */
  $(document).on("click", "#save-seo-name", function () {
    console.log("Click");

    // Read the post ID from the custom "media-id" attribute on the button
    let post_id = $(this).attr("media-id");

    // Read the new SEO name typed by the user in the custom input field
    var seo_name = $("#attachments-" + post_id + "-fmrseo_image_seo_name").val();

    console.log("Post ID:", post_id);
    console.log("SEO Name:", seo_name);

    /**
     * Send the new name to the server via AJAX.
     * The AJAX URL and nonce are injected by PHP via wp_localize_script (renameMedia object).
     */
    $.post(
      renameMedia.ajax_url,
      {
        action:      "fmrseo_save_seo_name",
        post_id:     post_id,
        seo_name:    seo_name,
        _ajax_nonce: renameMedia.nonce,
      },
      function (response) {
        console.log(response);

        if (response.success) {
          console.log("SEO Name saved successfully!");

          // Update the "Copy Link" field in the modal with the new file URL
          $("#attachment-details-two-column-copy-link").val(response.data.url);

          // Refresh the thumbnail shown in the Media Library grid.
          // We append a timestamp to the URL to bypass the browser's image cache,
          // otherwise the old image would still be displayed until a hard refresh.
          const thumbnail = $('.attachment[data-id="' + post_id + '"] img');
          if (thumbnail.length > 0) {
            const newSrc = response.data.url + "?v=" + new Date().getTime();
            thumbnail.attr("src", newSrc);
          } else {
            console.warn("Thumbnail not found in the media library!");
          }

          // Force a full page reload so the library reflects the renamed file
          window.location.reload(true);

        } else {
          // Show the error message returned by the server
          alert(response.data.message);
        }
      }
    );
  });

  // ─── AI RENAME (AI Rename button) ───────────────────────────────────────────

  /**
   * Handles the click on the AI Rename button inside the media modal.
   *
   * When clicked, the button is disabled and its label changes to a spinner
   * while the server analyses the image and suggests a new SEO-friendly name.
   * Once the server responds, the input field and thumbnail are updated automatically.
   *
   * We use $(document).on() here too because the button is inside the dynamic media modal.
   */
  $(document).on("click", ".fmrseo-ai-rename-button", function () {

    // AI rename requires both the feature flag and a configured API key on the server
    if (!renameMedia.ai_enabled) {
      alert(renameMedia.strings.ai_disabled);
      return;
    }

    if (!renameMedia.ai_key_set) {
      alert(renameMedia.strings.ai_missing_key);
      return;
    }

    const $button = $(this);
    const post_id = Number($button.data("media-id"));

    // Safety check: if the post ID is missing or invalid, do nothing
    if (!post_id) {
      return;
    }

    // Save the original button HTML so we can restore it after the request completes
    const originalHtml = $button.html();

    // Disable the button and show a spinner + "Processing…" label while waiting
    $button
      .prop("disabled", true)
      .html(
        '<span class="dashicons dashicons-update-alt" aria-hidden="true"></span>' +
        '<span class="fmrseo-ai-button-text">' + renameMedia.strings.ai_processing + "</span>"
      );

    console.log("AI rename requested for post ID:", post_id);

    /**
     * Send the request to the server.
     * The server will read the image, call the AI API, rename the file, and return the new data.
     */
    $.post(
      renameMedia.ajax_url,
      {
        action:  "fmrseo_ai_rename",
        post_id: post_id,
        nonce:   renameMedia.ai_nonce,
      },
      function (response) {
        console.log("AI rename response:", response);

        if (response.success) {

          // Update the "Copy Link" field with the new file URL
          if (response.data.url) {
            $("#attachment-details-two-column-copy-link").val(response.data.url);
          }

          // Fill the SEO name input with the AI-generated name
          if (response.data.seo_name) {
            $("#attachments-" + post_id + "-fmrseo_image_seo_name").val(response.data.seo_name);
          }

          // Refresh the thumbnail in the Media Library grid (same cache-busting trick as above)
          const thumbnail = $('.attachment[data-id="' + post_id + '"] img');
          if (thumbnail.length > 0 && response.data.url) {
            const newSrc = response.data.url + "?v=" + new Date().getTime();
            thumbnail.attr("src", newSrc);
          }

          // Reload the page to reflect all changes in the media library
          window.location.reload(true);

        } else {
          alert(response.data.message || renameMedia.strings.ai_error);
        }
      }
    )
      .fail(function () {
        // Generic error shown when the AJAX request itself fails (network error, 500, etc.)
        alert(renameMedia.strings.ai_error);
      })
      .always(function () {
        // Always re-enable the button and restore its original label,
        // regardless of whether the request succeeded or failed
        $button.prop("disabled", false).html(originalHtml);
      });
  });

  // ─── UNDO RENAME (History list) ─────────────────────────────────────────────

  /**
   * Handles clicks on previous names shown in the rename history list (#history-fmrseo).
   *
   * Clicking a past name copies it back into the SEO name input field,
   * letting the user quickly revert to an older name without typing.
   * The user still needs to click "Save" to apply the change.
   */
  $(document).on("click", "#history-fmrseo li", function () {
    let old_v   = $(this).text();    // The old name shown in the list item
    let post_id = $(this).attr("media-id");

    if (old_v) {
      // Paste the selected historical name back into the input field
      $("#attachments-" + post_id + "-fmrseo_image_seo_name").val(old_v);
    }
  });

});