/**
 * PDF Viewer Component using PDF.js
 *
 * Handles PDF rendering with canvas, page navigation, and error handling.
 * Uses PDF.js web worker for performance.
 */

import * as pdfjsLib from 'pdfjs-dist';

// Configure PDF.js worker
// Try CDN first for reliability, fall back to local build
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.8.69/pdf.worker.min.mjs';

export class PdfViewer {
    constructor(canvasElement, options = {}) {
        if (!canvasElement) {
            throw new Error('Canvas element is required');
        }

        this.canvas = canvasElement;
        this.context = this.canvas.getContext('2d');
        this.pdfDoc = null;
        this.currentPage = 1;
        this.scale = options.scale || 1.5;
        this.rendering = false;

        // Callbacks
        this.onPageChange = options.onPageChange || (() => {});
        this.onError = options.onError || ((error) => console.error('PDF Error:', error));
    }

    /**
     * Load PDF from URL
     * @param {string} url - PDF file URL (must be signed URL for security)
     * @returns {Promise<void>}
     */
    async loadDocument(url) {
        try {
            const loadingTask = pdfjsLib.getDocument(url);

            this.pdfDoc = await loadingTask.promise;
            this.currentPage = 1;

            await this.renderPage(1);
            this.onPageChange(1, this.pdfDoc.numPages);

        } catch (error) {
            this.onError(error);
            throw new Error(`Failed to load PDF: ${error.message}`);
        }
    }

    /**
     * Render specific page
     * @param {number} pageNum - Page number to render (1-indexed)
     * @returns {Promise<void>}
     */
    async renderPage(pageNum) {
        if (!this.pdfDoc) {
            throw new Error('No PDF document loaded');
        }

        if (this.rendering) {
            return; // Prevent concurrent renders
        }

        if (pageNum < 1 || pageNum > this.pdfDoc.numPages) {
            throw new Error(`Invalid page number: ${pageNum}`);
        }

        try {
            this.rendering = true;
            this.currentPage = pageNum;

            const page = await this.pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale: this.scale });

            // Set canvas dimensions
            this.canvas.height = viewport.height;
            this.canvas.width = viewport.width;

            const renderContext = {
                canvasContext: this.context,
                viewport: viewport,
            };

            await page.render(renderContext).promise;
            this.onPageChange(pageNum, this.pdfDoc.numPages);

        } catch (error) {
            this.onError(error);
            throw new Error(`Failed to render page ${pageNum}: ${error.message}`);
        } finally {
            this.rendering = false;
        }
    }

    /**
     * Navigate to next page
     * @returns {Promise<void>}
     */
    async nextPage() {
        if (!this.pdfDoc || this.currentPage >= this.pdfDoc.numPages) {
            return;
        }
        await this.renderPage(this.currentPage + 1);
    }

    /**
     * Navigate to previous page
     * @returns {Promise<void>}
     */
    async previousPage() {
        if (!this.pdfDoc || this.currentPage <= 1) {
            return;
        }
        await this.renderPage(this.currentPage - 1);
    }

    /**
     * Zoom in
     * @returns {Promise<void>}
     */
    async zoomIn() {
        this.scale += 0.25;
        await this.renderPage(this.currentPage);
    }

    /**
     * Zoom out
     * @returns {Promise<void>}
     */
    async zoomOut() {
        if (this.scale > 0.5) {
            this.scale -= 0.25;
            await this.renderPage(this.currentPage);
        }
    }

    /**
     * Get current state
     * @returns {Object}
     */
    getState() {
        return {
            currentPage: this.currentPage,
            totalPages: this.pdfDoc?.numPages || 0,
            scale: this.scale,
            loaded: !!this.pdfDoc,
        };
    }

    /**
     * Clean up resources
     */
    destroy() {
        if (this.pdfDoc) {
            this.pdfDoc.destroy();
            this.pdfDoc = null;
        }
        this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
    }
}

// Export for global use in Livewire components
window.PdfViewer = PdfViewer;
