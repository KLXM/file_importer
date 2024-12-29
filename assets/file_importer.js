$(document).on('rex:ready', function() {
    const FileImporter = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        currentProvider: 'pixabay',
        
        init: function() {
            this.bindEvents();
            this.initInfiniteScroll();
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
            
            // Vorschau Button
            $(document).on('click', '.file-importer-preview-btn', (e) => {
                e.preventDefault();
                const btn = $(e.currentTarget);
                const item = btn.closest('.file-importer-item');
                this.showPreview(item);
            });
            
            // Import Button
            $(document).on('click', '.file-importer-import-btn', (e) => {
                e.preventDefault();
                const btn = $(e.currentTarget);
                const item = btn.closest('.file-importer-item');
                this.importFile(item);
            });
            
            // Bildgröße ändern
            $(document).on('change', '.file-importer-size-select', function() {
                const url = $(this).find('option:selected').data('url');
                $(this).closest('.file-importer-item').data('download-url', url);
            });
        },
        
        initInfiniteScroll: function() {
            const options = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && this.hasMore && !this.loading) {
                        this.loadMore();
                    }
                });
            }, options);
            
            observer.observe($('#file-importer-loadmore')[0]);
        },
        
        loadResults: function() {
            if (this.loading) return;
            
            this.loading = true;
            this.updateStatus('loading');
            
            $.ajax({
                url: 'index.php',
                data: {
                    page: 'file_importer/main',
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
                        this.showError(response.error);
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
            this.currentPage++;
            this.loadResults();
        },
        
        renderResults: function(data) {
            const container = $('#file-importer-results');
            const template = $('#file-importer-template').html();
            
            data.items.forEach(item => {
                const html = template
                    .replace(/\{preview_url\}/g, item.preview_url)
                    .replace(/\{title\}/g, item.title)
                    .replace(/\{author\}/g, item.author)
                    .replace(/\{download_url\}/g, item.download_url);
                    
                const element = $(html);
                
                // Größenauswahl aufbauen
                const sizeSelect = element.find('.file-importer-size-select');
                Object.entries(item.size).forEach(([key, value]) => {
                    sizeSelect.append(
                        $('<option></option>')
                            .attr('value', key)
                            .attr('data-url', value.url)
                            .text(key.charAt(0).toUpperCase() + key.slice(1))
                    );
                });
                
                container.append(element);
            });
            
            this.hasMore = data.page < data.total_pages;
            this.updateStatus('results', data.total);
        },
        
        showPreview: function(item) {
            const previewUrl = item.find('img').attr('src');
            const title = item.find('.file-importer-title').text();
            
            const modal = $(`
                <div class="modal fade file-importer-preview-modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <h4 class="modal-title">${title}</h4>
                            </div>
                            <div class="modal-body">
                                <img src="${previewUrl}" alt="${title}">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">
                                    Schließen
                                </button>
                                <button type="button" class="btn btn-primary file-importer-import-btn">
                                    <i class="rex-icon fa-download"></i> Importieren
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            modal.modal('show');
            modal.find('.file-importer-import-btn').on('click', () => {
                this.importFile(item);
                modal.modal('hide');
            });
        },
        
        importFile: function(item) {
            const btn = item.find('.file-importer-import-btn');
            const progress = item.find('.file-importer-progress');
            const url = item.find('.file-importer-size-select option:selected').data('url');
            const filename = item.find('.file-importer-title').text();
            const categoryId = $('#rex-mediapool-category').val();
            
            btn.prop('disabled', true);
            progress.show();
            
            $.ajax({
                url: 'index.php',
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
                    if (response.success) {
                        this.showSuccess('Die Datei wurde erfolgreich importiert');
                    } else {
                        this.showError(response.error || 'Import failed');
                    }
                },
                error: (xhr, status, error) => {
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
                        status.text(`${total} Ergebnisse gefunden`);
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
