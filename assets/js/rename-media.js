jQuery(document).ready(function ($) {
  /**
   * Handles the click event on the "Save SEO Name" button to save the SEO name of the file.
   * Uses $(document).on("click", "#save-seo-name") to handle events on dynamically loaded elements.
   */
  $(document).on("click", "#save-seo-name", function () {
    console.log("Click");

    // Get the media ID to be modified from the "media-id" attribute of the button
    let post_id = $(this).attr("media-id");

    // Get the value of the input field to change the file name
    var seo_name = $("#attachments-" + post_id + "-fmrseo_image_seo_name").val();

    console.log("Post ID:", post_id);
    console.log("SEO Name:", seo_name);

    /**
     * Sends an AJAX request to save the SEO name.
     * Uses the AJAX URL and nonce passed via wp_localize_script.
     */
    $.post(
      renameMedia.ajax_url,
      {
        action: "fmrseo_save_seo_name",
        post_id: post_id,
        seo_name: seo_name,
        _ajax_nonce: renameMedia.nonce,
      },
      function (response) {
        console.log(response);

        // If the response is successful, update the value of the input field in the modal
        if (response.success) {
          console.log("SEO Name saved successfully!");

          // Update the value of the input field in the modal
          $("#attachment-details-two-column-copy-link").val(response.data.url);

          // Update the thumbnail in the Media Library for bypasss cache
          const thumbnail = $('.attachment[data-id="' + post_id + '"] img');
          if (thumbnail.length > 0) {
            // Force reload by adding a unique parameter to bypass the cache
            const newSrc = response.data.url + "?v=" + new Date().getTime();
            thumbnail.attr("src", newSrc);
          } else {
            console.warn("Thumbnail not found in the media library!");
          }
          window.location.reload(true);
        } else if (!response.success) {
          // if success false then show alert with message
          alert(response.data.message);
        }
      }
    );
  });

  // Undo rename handler
  $(document).on("click", "#history-fmrseo li", function (e) {
    let old_v = $(this).text();
    console.log("🚀 ~ old_v:", old_v)
    
    let post_id = $(this).attr("media-id");

    if(old_v){
        $("#attachments-" + post_id + "-fmrseo_image_seo_name").val(old_v);

    }
  });
});

