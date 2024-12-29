$(document).on('rex:ready', function() {
    const FileImporter = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        currentProvider: 'pixabay',
        
        init: function() {
            console.log('FileImporter initialized');
            this.bindEvents();
            $('#file-importer-results').empty();
            $('#file-importer-status').empty();
        },
        
        bindEvents: function() {
            // Suchformular
            $('#file-importer-search').on('submit', (e) => {
                e.preventDefault();
                const query = $('#file-importer-query').val();
                console.log('Search submitted:', query);
                
                this.currentQuery = query;
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
                
                console.log('Import clicked:', { url, filename });
                this.importFile(url, filename, btn);
            });
        },
        
        loadResults: function() {
            if (this.loading) {
                console.log('Already loading, skipping request');
                return;
            }
            
            this.loading = true;
            this.updateStatus('loading');
            
            const params = {
                page: 'file_importer/main',
                file_importer_api: 1,
                action: 'search',
                provider: this.currentProvider,
                query: this.currentQuery,
                page: this.currentPage
            };

            console.log('Loading results with params:', params);
            
            $.ajax({
                url: window.location.pathname,
                method: 'GET',
                data: params,
                success: (response) => {
                    console.log('Search response:', response);
                    if (response.success) {
                        this.renderResults(response.data);
                    } else {
                        this.showError(response.error || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Search error:', { status, error, xhr });
                    this.showError('Error loading results: ' + error);
                },
                complete: () => {
                    this.loading = false;
                }
            });
        },
        
        renderResults: function(data) {
            console.log('Rendering results:', data);
            const container = $('#file-importer-results');
            let html = '';
            
            if (!data.items || !data.items.length) {
                container.html('<div class="alert alert-info">Keine Ergebnisse gefunden</div>');
                this.hasMore = false;
                return;
            }
            
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
            
            if (this.currentPage === 1) {
                container.html(html);
            } else {
                container.append(html);
            }
            
            this.hasMore = data.page < data.total_pages;
            this.updateStatus('results', data.total);
            
            // Infinite Scroll
            if (this.hasMore) {
                $(window).off('scroll.fileimporter').on('scroll.fileimporter', () => {
                    if ($(window).scrollTop() + $(window).height() > $(document).height() - 100) {
                        if (!this.loading) {
                            this.currentPage++;
                            this.loadResults();
                        }
                    }
                });
            }
        },
        
        importFile: function(url, filename, btn) {
            console.log('Starting import:', { url, filename });
            
            const progress = btn.closest('.file-importer-item').find('.file-importer-progress');
            const categoryId = $('#rex-mediapool-category').val();
            
            btn.prop('disabled', true);
            progress.show();
            
            $.ajax({
                url: window.location.pathname,
                method: 'POST',
                data: {
                    page: 'file_importer/main',
                    file_importer_api: 1,
                    action: 'download',
                    provider: this.currentProvider,
                    url: url,
                    filename: filename,
                    category_id: categoryId
                },
                success: (response) => {
                    console.log('Import response:', response);
                    if (response.success) {
                        this.showSuccess('Die Datei wurde erfolgreich importiert');
                    } else {
                        this.showError(response.error || 'Import fehlgeschlagen');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Import error:', { status, error, xhr });
                    this.showError('Error importing file: ' + error);
                },
                complete: () => {
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
            console.error('Error:', message);
            const alert = $('<div class="alert alert-danger"></div>').text(message);
            $('#file-importer-alerts').empty().append(alert);
            
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        },
        
        showSuccess: function(message) {
            console.log('Success:', message);
            const alert = $('<div class="alert alert-success"></div>').text(message);
            $('#file-importer-alerts').empty().append(alert);
            
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 3000);
        }
    };
    
    FileImporter.init();
});
