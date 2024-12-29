$(document).on('rex:ready', function() {
    const FileImporter = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        currentProvider: 'pixabay',
        
        init: function() {
            this.bindEvents();
            
            // Nicht mehr automatisch laden
            $('#file-importer-results').empty();
            $('#file-importer-status').empty();
        },
        
        bindEvents: function() {
            // Suchformular
            $('#file-importer-search').on('submit', (e) => {
                e.preventDefault();
                this.currentQuery = $('#file-importer-query').val();
                this.currentPage = 1;
                this.hasMore = true;
                $('#file-importer-results').empty();
                this.loadResults();
            });
            
            // Import Button
            $(document).on('click', '.file-importer-import-btn', (e) => {
                e.preventDefault();
                const btn = $(e.currentTarget);
                const item = btn.closest('.file-importer-item');
                const selectSize = item.find('.file-importer-size-select');
                const url = selectSize.find('option:selected').data('url');
                const filename = item.find('.file-importer-title').text();
                
                this.importFile(url, filename, btn);
            });
            
            // Größenauswahl ändern
            $(document).on('change', '.file-importer-size-select', function() {
                const url = $(this).find('option:selected').data('url');
                $(this).closest('.file-importer-item').data('download-url', url);
            });

            // Lazy Load für weitere Seiten
            $(window).on('scroll', () => {
                if (this.hasMore && !this.loading) {
                    const scrollPos = $(window).scrollTop() + $(window).height();
                    const threshold = $(document).height() - 100;
                    
                    if (scrollPos > threshold) {
                        this.loadMore();
                    }
                }
            });
        },
        
        loadResults: function() {
            if (this.loading) return;
            
            this.loading = true;
            this.updateStatus('loading');
            
            console.log('Starting search with params:', {
                query: this.currentQuery,
                page: this.currentPage,
                provider: this.currentProvider
            });
            
            console.log('Searching for:', this.currentQuery); // Debug
            
            $.ajax({
                url: window.location.href,
                data: {
                    file_importer_api: 1,
                    action: 'search',
                    provider: this.currentProvider,
                    query: this.currentQuery,
                    page: this.currentPage
                },
                success: (response) => {
                    if (response.success) {
                        this.renderResults(response.data);
                    } else {
                        this.showError(response.error || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error loading results: ' + error);
                },
                complete: () => {
                    this.loading = false;
                }
            });
        },
        
        loadMore: function() {
            if (this.hasMore && !this.loading) {
                this.currentPage++;
                this.loadResults();
            }
        },
        
        renderResults: function(data) {
            const container = $('#file-importer-results');
            let html = '';
            
            data.items.forEach(item => {
                html += `
                    <div class="file-importer-item">
                        <div class="file-importer-preview">
                            <img src="${item.preview_url}" alt="${item.title}" loading="lazy">
                        </div>
                        <div class="file-importer-info">
                            <div class="file-importer-title">${item.title}</div>
                            <select class="form-control file-importer-size-select">
                                ${Object.entries(item.size).map(([key, value]) => `
                                    <option value="${key}" data-url="${value.url}">
                                        ${key.charAt(0).toUpperCase() + key.slice(1)}
                                    </option>
                                `).join('')}
                            </select>
                            <div class="file-importer-actions">
                                <button class="btn btn-primary btn-block file-importer-import-btn">
                                    <i class="rex-icon fa-download"></i> Importieren
                                </button>
                            </div>
                            <div class="progress file-importer-progress" style="display: none;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    Importiere...
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.append(html);
            
            this.hasMore = data.page < data.total_pages;
            this.updateStatus('results', data.total);
        },
        
        importFile: function(url, filename, btn) {
            const progress = btn.closest('.file-importer-item').find('.file-importer-progress');
            const categoryId = $('#rex-mediapool-category').val();
            
            btn.prop('disabled', true);
            progress.show();
            
            $.ajax({
                url: window.location.pathname,
                method: 'POST',
                data: {
                    file_importer_api: 1,
                    action: 'download',
                    provider: this.currentProvider,
                    url: url,
                    filename: filename,
                    category_id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Die Datei wurde erfolgreich importiert');
                        setTimeout(() => {
                            btn.prop('disabled', false);
                            progress.hide();
                        }, 1000);
                    } else {
                        this.showError(response.error || 'Import fehlgeschlagen');
                        btn.prop('disabled', false);
                        progress.hide();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error importing file: ' + error);
                    btn.prop('disabled', false);
                    progress.hide();
                }
            });
        },
        
        updateStatus: function(type, total = 0) {
            const status = $('#file-importer-status');
            
            switch(type) {
                case 'loading':
                    status.html('<i class="rex-icon fa-spinner fa-spin"></i> Lade Ergebnisse...');
                    break;
                case 'results':
                    if (total > 0) {
                        status.text(total + ' Ergebnisse gefunden');
                    } else {
                        status.text('Keine Ergebnisse gefunden');
                    }
                    break;
                default:
                    status.empty();
            }
        },
        
        showError: function(message) {
            const alert = $('<div class="alert alert-danger"></div>').text(message);
            $('#file-importer-alerts').empty().append(alert);
            
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        },
        
        showSuccess: function(message) {
            const alert = $('<div class="alert alert-success"></div>').text(message);
            $('#file-importer-alerts').empty().append(alert);
            
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 3000);
        }
    };
    
    FileImporter.init();
});
