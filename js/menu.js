jQuery(document).ready(function($) {
    // Cache DOM elements
    const $menu = $('#wp-admin-bar-wpe-sites');
    const $searchInput = $menu.find('.wpe-search-container input');
    const $searchResults = $('#wp-admin-bar-wpe-search-results');
    let searchTimeout = null;

    // Search functionality
    $searchInput.on('input', function(e) {
        e.stopPropagation();
        const searchTerm = $(this).val().trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Clear results if search is empty
        if (searchTerm === '') {
            $searchResults.empty();
            return;
        }
        
        // Set new timeout for search
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: wpeMenu.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_wpe_sites',
                    nonce: wpeMenu.nonce,
                    search: searchTerm
                },
                beforeSend: function() {
                    $searchResults.html('<div class="ab-item wpe-no-results">Searching...</div>');
                },
                success: function(response) {
                    if (response.success && response.data.results) {
                        const results = response.data.results;
                        
                        if (results.length === 0) {
                            $searchResults.html('<div class="ab-item wpe-no-results">No matches found</div>');
                            return;
                        }
                        
                        // Group results by site
                        const groupedResults = {};
                        results.forEach(function(result) {
                            if (!groupedResults[result.site_name]) {
                                groupedResults[result.site_name] = [];
                            }
                            groupedResults[result.site_name].push(result);
                        });
                        
                        // Build results HTML
                        let html = '';
                        Object.keys(groupedResults).forEach(function(siteName) {
                            const siteResults = groupedResults[siteName];
                            siteResults.forEach(function(result) {
                                html += '<a class="ab-item" href="' + result.url + '">' +
                                    result.install_name + ' (' + result.environment + ')</a>';
                            });
                        });
                        
                        $searchResults.html(html);
                    } else {
                        $searchResults.html('<div class="ab-item wpe-no-results">Error loading results</div>');
                    }
                },
                error: function() {
                    $searchResults.html('<div class="ab-item wpe-no-results">Error loading results</div>');
                }
            });
        }, 300); // Debounce search requests
    });
    
    // Prevent search input from closing menu
    $searchInput.on('click', function(e) {
        e.stopPropagation();
    });
    
    // Clear search when menu closes
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#wp-admin-bar-wpe-sites').length) {
            $searchInput.val('');
            $searchResults.empty();
        }
    });
});
