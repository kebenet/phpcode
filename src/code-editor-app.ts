import { LitElement, html, css, PropertyValues } from 'lit';
import { customElement, property, state, query } from 'lit/decorators.js';

// Import child components
import './file-explorer.js';
import './editor-tabs.js';
import './ace-editor.js';
import './notification-toast.js';

// Import types from child components
import type { FileExplorerItem } from './file-explorer.js';
import type { TabInfo as EditorTabInfo } from './editor-tabs.js'; // Renamed to avoid conflict
import type { NotificationToast, NotificationType } from './notification-toast.js';
import type { AceEditor } from './ace-editor.js';

// Ace types - assuming Ace is loaded globally.
// For a packaged setup, you'd import from 'ace-builds'.
declare var ace: {
  edit: (el: HTMLElement | string) => Ace.Editor;
  createEditSession: (text: string, mode: string) => Ace.EditSession;
  [key: string]: any;
};

declare namespace Ace {
  interface Editor {
    setSession(session: EditSession): void;
    getSession(): EditSession;
    // ... other methods if needed
  }
  interface EditSession {
    getValue(): string;
    setValue(val: string): void; // Simplified, no cursor
    setMode(mode: string): void;
    getMode(): any;
    setUndoManager(undoManager: UndoManager): void;
    getUndoManager(): UndoManager;
    on(event: string, callback: (delta: any, session: EditSession) => void): void; // More specific 'change'
    off(event: string, callback: (...args: any[]) => void): void;
    [key: string]: any;
  }
  interface UndoManager {
    markClean(): void;
    isClean(): boolean;
    reset(): void;
    hasUndo(): boolean;
    hasRedo(): boolean;
    [key: string]: any;
  }
}


// Define the structure for directory listing results from the backend
interface DirectoryListing {
  path: string;
  parentPath: string | null;
  items: FileExplorerItem[];
  error?: string;
}

// Updated Tab interface to include Ace.EditSession
interface OpenTabInfo extends EditorTabInfo { // Inherits id, name, path, unsavedChanges
  editSession: Ace.EditSession;
  fileMode: string; // To store the mode string like "ace/mode/php"
  isLoading?: boolean;
  // initialContent is implicitly handled by UndoManager.isClean()
}


@customElement('code-editor-app')
export class CodeEditorApp extends LitElement {
  static styles = css`
    :host {
      display: flex;
      height: calc(100vh - 4rem); /* Assuming navbar is 4rem, adjust if needed */
      font-family: sans-serif;
      box-sizing: border-box;
    }
    .sidebar {
      width: 280px;
      min-width: 200px;
      border-right: 1px solid #ccc;
      padding: 0;
      overflow-y: auto;
      background-color: #f9f9f9;
      display: flex;
      flex-direction: column;
    }
    .main-area {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background-color: #fff;
    }
    .editor-section {
      flex-grow: 1;
      position: relative;
      display: flex;
      flex-direction: column;
    }
    .placeholder-editor {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #aaa;
        background-color: #f8f8f8;
    }
    .placeholder-editor i {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    ace-editor {
      flex-grow: 1;
    }
    .loading {
      text-align: center;
      padding: 20px;
      color: #777;
    }
    .error-message {
      padding: 10px;
      background-color: #ffdddd;
      color: #d8000c;
      border: 1px solid #d8000c;
      margin: 10px;
      border-radius: 4px;
    }
  `;

  @property({ type: String })
  currentPath: string = '/';

  @state()
  private _directoryListing: DirectoryListing = { path: '/', parentPath: null, items: [] };

  @state()
  private _openTabs: OpenTabInfo[] = [];

  @state()
  private _activeTabId: string | null = null;

  @state()
  private _isLoadingDirectory: boolean = false;

  // _activeFileContent and _activeFileMode are no longer primary states for editor.
  // They are derived from the active tab's session.
  
  @state()
  private _globalError: string | null = null;

  @state()
  private _canSave: boolean = false;

  @state()
  private _saveButtonTooltip: string = 'No active file to save';

  @query('#notificationToast')
  private _notificationToast!: NotificationToast;
  
  @query('ace-editor')
  private _aceEditorInstance!: AceEditor;

  // Store a map of tabId to its session change handler for easy removal
  private _sessionChangeHandlers: Map<string, (delta: any, session: Ace.EditSession) => void> = new Map();


  constructor() {
    super();
    this._handleGlobalSave = this._handleGlobalSave.bind(this);
  }

  connectedCallback() {
    super.connectedCallback();
    window.addEventListener('save-file-triggered', this._handleGlobalSave);
    this.fetchDirectoryListing(this.currentPath);
    this._updateSaveButtonState(); 
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    window.removeEventListener('save-file-triggered', this._handleGlobalSave);
    // Clean up session listeners
    this._openTabs.forEach(tab => {
        const handler = this._sessionChangeHandlers.get(tab.id);
        if (handler) {
            tab.editSession.off('change', handler);
        }
    });
    this._sessionChangeHandlers.clear();
  }

  protected updated(changedProperties: PropertyValues) {
    super.updated(changedProperties);
    if (changedProperties.has('_activeTabId') || changedProperties.has('_openTabs')) {
      this._updateSaveButtonState();
    }
    if (changedProperties.has('_canSave') || changedProperties.has('_saveButtonTooltip')) {
        this._dispatchSaveStateChanged();
    }
  }

  private _dispatchSaveStateChanged() {
    window.dispatchEvent(new CustomEvent('save-state-changed', {
        detail: { canSave: this._canSave, saveTooltip: this._saveButtonTooltip },
        bubbles: true, composed: true
    }));
  }

  private _updateSaveButtonState() {
    const activeTab = this.activeTab;
    if (activeTab && activeTab.unsavedChanges) {
      this._canSave = true;
      this._saveButtonTooltip = `Save ${activeTab.name} (Ctrl+S / Cmd+S)`;
    } else if (activeTab) {
      this._canSave = false;
      this._saveButtonTooltip = `No unsaved changes in ${activeTab.name}`;
    } else {
      this._canSave = false;
      this._saveButtonTooltip = 'No active file to save';
    }
  }

  private _handleGlobalSave() {
    if (this._canSave && this.activeTab) {
      this._saveActiveFile();
    } else if (this.activeTab) {
        this._showNotification('No unsaved changes to save.', 'info');
    } else {
      this._showNotification('No active file to save.', 'info');
    }
  }

  async fetchDirectoryListing(path: string) {
    this._isLoadingDirectory = true;
    this._globalError = null;
    try {
      const response = await fetch(`folder.php?action=list&path=${encodeURIComponent(path)}`);
      if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        throw new Error(errorData?.error || `HTTP error! status: ${response.status}`);
      }
      const data: DirectoryListing = await response.json();
      if (data.error) { throw new Error(data.error); }
      data.items = Array.isArray(data.items) ? data.items : [];
      this._directoryListing = { ...data, items: this._sortFiles(data.items) };
      this.currentPath = data.path;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      this._globalError = `Failed to load directory '${path}': ${errorMessage}`;
      this._directoryListing = { path, parentPath: this._calculateParentPath(path), items: [] };
      this._showNotification(this._globalError, 'error', 5000);
    } finally {
      this._isLoadingDirectory = false;
    }
  }

  private _calculateParentPath(path: string): string | null { /* ... (no change) ... */ 
    if (path === '/' || path === '.') return null;
    const lastSlash = path.lastIndexOf('/');
    if (lastSlash === -1) return '.';
    if (lastSlash === 0) return '/';
    return path.substring(0, lastSlash);
  }
  private _sortFiles(files: FileExplorerItem[]): FileExplorerItem[] { /* ... (no change) ... */ 
    return files.sort((a, b) => {
      if (a.type === b.type) {
        return a.name.localeCompare(b.name);
      }
      return a.type === 'directory' ? -1 : 1;
    });
  }

  async fetchFileContent(filePath: string): Promise<{ content: string; error?: string }> { /* ... (no change) ... */
    try {
      const response = await fetch(`folder.php?action=get_content&path=${encodeURIComponent(filePath)}`);
      if (!response.ok) {
         const errorData = await response.json().catch(() => ({ error: `Failed to fetch file: ${response.status} ${response.statusText}` }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      if (data.error) {
        throw new Error(data.error);
      }
      return { content: data.content };
    } catch (error) {
      console.error('Error fetching file content:', error);
      const errorMessage = error instanceof Error ? error.message : String(error);
      return { content: `// Error loading file: ${errorMessage}`, error: errorMessage };
    }
  }

  private _getFileMode(fileName: string): string { // Renamed from _determineAceMode
    const ext = fileName.split('.').pop()?.toLowerCase();
    switch (ext) {
      case 'php': return 'ace/mode/php';
      case 'js': return 'ace/mode/javascript';
      case 'css': return 'ace/mode/css';
      case 'html': return 'ace/mode/html';
      case 'md': return 'ace/mode/markdown';
      case 'json': return 'ace/mode/json';
      case 'ini': return 'ace/mode/ini';
      case 'xml': return 'ace/mode/xml';
      case 'sql': return 'ace/mode/sql';
      case 'sh': return 'ace/mode/sh';
      case 'py': return 'ace/mode/python';
      case 'java': return 'ace/mode/java';
      case 'c': case 'cpp': case 'h': return 'ace/mode/c_cpp';
      default: return 'ace/mode/text';
    }
  }

  private async _handleFileSelected(event: CustomEvent<FileExplorerItem>) {
    const file = event.detail;
    const existingTab = this._openTabs.find(tab => tab.path === file.path);

    if (existingTab) {
      this._activeTabId = existingTab.id;
      this._updateSaveButtonState();
      this.requestUpdate(); // Ensure ace-editor gets the session if it wasn't active
      return;
    }

    const tabId = `tab-${file.path.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
    const fileMode = this._getFileMode(file.name);
    
    // Create a temporary loading session
    const loadingSession = ace.createEditSession(`// Loading ${file.name}...`, "ace/mode/text");
    loadingSession.setUndoManager(new ace.UndoManager()); // Give it an undo manager

    const newTab: OpenTabInfo = {
      id: tabId, name: file.name, path: file.path,
      editSession: loadingSession, // Use loading session initially
      fileMode: fileMode,
      unsavedChanges: false, // Initially no unsaved changes
      isLoading: true,
    };

    this._openTabs = [...this._openTabs, newTab];
    this._activeTabId = tabId;
    this._updateSaveButtonState();
    this.requestUpdate(); // Ensure new tab and editor are rendered

    const { content: fileContent, error } = await this.fetchFileContent(file.path);
    
    const targetTab = this._openTabs.find(t => t.id === tabId);
    if (!targetTab) return; // Tab might have been closed quickly

    if (error) {
      this._showNotification(`Error loading ${file.name}: ${error}`, 'error');
      const errorSession = ace.createEditSession(`// Error loading ${file.name}: ${error}`, "ace/mode/text");
      errorSession.setUndoManager(new ace.UndoManager());
      targetTab.editSession = errorSession;
      targetTab.isLoading = false;
    } else {
      const actualSession = ace.createEditSession(fileContent, targetTab.fileMode);
      actualSession.setUndoManager(new ace.UndoManager());
      
      const changeHandler = (_delta: any, sessionInstance: Ace.EditSession) => {
        // Check if the session still belongs to an open tab
        const currentTabForSession = this._openTabs.find(t => t.editSession === sessionInstance);
        if (currentTabForSession) {
            // Check undo manager status BEFORE changing our flag
            const wasClean = currentTabForSession.editSession.getUndoManager().isClean();
            // Ace change event fires AFTER the change, so isClean is now false if it was the first change
            const isNowDirty = !currentTabForSession.editSession.getUndoManager().isClean();

            if (isNowDirty && !currentTabForSession.unsavedChanges) {
                 currentTabForSession.unsavedChanges = true;
                 this._updateSaveButtonState();
                 this.requestUpdate('_openTabs'); // For tab UI
            } else if (!isNowDirty && currentTabForSession.unsavedChanges) {
                // This case might happen if user undoes to clean state
                 currentTabForSession.unsavedChanges = false;
                 this._updateSaveButtonState();
                 this.requestUpdate('_openTabs');
            }
        }
      };
      actualSession.on('change', changeHandler);
      this._sessionChangeHandlers.set(tabId, changeHandler); // Store for cleanup

      targetTab.editSession = actualSession;
      targetTab.isLoading = false;
      targetTab.unsavedChanges = false; // Freshly loaded
    }
    
    this._openTabs = [...this._openTabs]; // Trigger update for tab list
    if (this._activeTabId === tabId) { // If still active, ensure ace-editor gets the new session
        this.requestUpdate();
    }
    this._updateSaveButtonState();
  }

  private async _handleFolderSelected(event: CustomEvent<FileExplorerItem>) { /* ... (no change) ... */ 
    await this.fetchDirectoryListing(event.detail.path);
  }
  private async _handleNavigateUp(event: CustomEvent<{ path: string }>) { /* ... (no change) ... */ 
    await this.fetchDirectoryListing(event.detail.path);
  }

  private _handleTabSelected(event: CustomEvent<{ tabId: string }>) {
    this._activeTabId = event.detail.tabId;
    this._updateSaveButtonState();
    // The render method will pass the correct session to ace-editor.
    // If ace-editor is already on screen, its `updated` lifecycle will handle session change.
    this.requestUpdate(); 
  }

  private _handleTabClosed(event: CustomEvent<{ tabId: string }>) {
    const tabIdToClose = event.detail.tabId;
    const tabToClose = this._openTabs.find(t => t.id === tabIdToClose);

    if (tabToClose && tabToClose.unsavedChanges) {
      if (!confirm(`File "${tabToClose.name}" has unsaved changes. Close anyway?`)) {
        return;
      }
    }
    
    // Clean up session listener
    const handler = this._sessionChangeHandlers.get(tabIdToClose);
    if (handler && tabToClose) {
        tabToClose.editSession.off('change', handler);
        this._sessionChangeHandlers.delete(tabIdToClose);
    }

    this._openTabs = this._openTabs.filter(tab => tab.id !== tabIdToClose);
    if (this._activeTabId === tabIdToClose) {
      this._activeTabId = this._openTabs.length > 0 ? this._openTabs[this._openTabs.length - 1].id : null;
    }
    this._updateSaveButtonState();
  }

  // _handleEditorChange is no longer needed as we listen to session 'change' directly.

  private async _saveActiveFile() {
    const activeTab = this.activeTab;
    if (!activeTab) {
      this._showNotification('Error: No active tab to save.', 'error');
      return;
    }
    
    // Check clean state with undo manager, not just our flag
    const isActuallyDirty = !activeTab.editSession.getUndoManager().isClean();
    if (!isActuallyDirty && !activeTab.unsavedChanges) { // Double check with our flag
        this._showNotification(`No changes to save for ${activeTab.name}.`, 'info');
        return;
    }

    this._showNotification(`Saving ${activeTab.name}...`, 'info', 1500);
    const contentToSave = activeTab.editSession.getValue();

    try {
      const formData = new URLSearchParams();
      formData.append('filePath', activeTab.path);
      formData.append('content', contentToSave);

      const response = await fetch(`save-file.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString(),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: `HTTP error ${response.status}` }));
        throw new Error(errorData.error || `Failed to save: ${response.statusText}`);
      }

      const result = await response.json();
      if (result.success) {
        this._showNotification(`File "${activeTab.name}" saved successfully!`, 'success');
        activeTab.editSession.getUndoManager().markClean();
        activeTab.unsavedChanges = false; // Sync our flag
        // this.requestUpdate('_openTabs'); // Handled by _updateSaveButtonState -> lit update cycle
      } else {
        throw new Error(result.error || 'Unknown server error during save.');
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to save file.';
      this._showNotification(errorMessage, 'error', 5000);
    }
    this._updateSaveButtonState(); // Will trigger reactive update for tab list as well
  }

  private _showNotification(message: string, type: NotificationType, duration?: number) { /* ... (no change) ... */ 
    if (this._notificationToast) {
      this._notificationToast.show(message, type, duration);
    } else {
      console.warn(`Notification Toast not available. Message: [${type}] ${message}`);
      alert(`[${type}] ${message}`);
    }
  }

  // Getter for the currently active tab object
  private get activeTab(): OpenTabInfo | undefined {
    return this._openTabs.find(t => t.id === this._activeTabId);
  }

  render() {
    const currentActiveTab = this.activeTab;
    const editorSession = currentActiveTab?.editSession;
    const editorIsLoading = currentActiveTab?.isLoading ?? false;

    return html`
      <div class="sidebar">
        ${this._isLoadingDirectory && !this._directoryListing.items.length
          ? html`<div class="loading">Loading directory...</div>` 
          : html`
              <file-explorer
                .items=${this._directoryListing.items}
                .currentPath=${this._directoryListing.path}
                .parentPath=${this._directoryListing.parentPath}
                @folder-selected=${this._handleFolderSelected}
                @file-selected=${this._handleFileSelected}
                @navigate-up=${this._handleNavigateUp}
              ></file-explorer>
            `}
        ${this._globalError && !this._isLoadingDirectory ? html`<div class="error-message">${this._globalError}</div>` : ''}
      </div>

      <div class="main-area">
        <editor-tabs
          .tabs=${this._openTabs.map(t => ({ id: t.id, name: t.name, path: t.path, unsavedChanges: t.unsavedChanges }))}
          .activeTabId=${this._activeTabId}
          @tab-selected=${this._handleTabSelected}
          @tab-closed=${this._handleTabClosed}
        ></editor-tabs>

        <div class="editor-section">
          ${currentActiveTab ? html`
            <ace-editor
              .session=${editorSession}
              .readonly=${editorIsLoading} 
              theme="ace/theme/tomorrow_night_eighties" 
            ></ace-editor> 
            <!-- Removed @editor-change, theme is now a static example -->
          ` : html`
            <div class="placeholder-editor">
                <i class="fas fa-file-code"></i>
                <p>Select a file to begin editing.</p>
                <p class="text-sm">Or open a folder from the explorer.</p>
            </div>
          `}
        </div>
      </div>
      <notification-toast id="notificationToast"></notification-toast>
    `;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'code-editor-app': CodeEditorApp;
  }
  interface DocumentEventMap {
    'save-file-triggered': CustomEvent;
    'save-state-changed': CustomEvent<{canSave: boolean, saveTooltip: string}>;
  }
  interface WindowEventMap { 
    'save-file-triggered': CustomEvent;
    'save-state-changed': CustomEvent<{canSave: boolean, saveTooltip: string}>;
  }
}
