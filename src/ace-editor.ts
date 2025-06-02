import { LitElement, html, css, PropertyValues } from 'lit';
import { customElement, property, query } from 'lit/decorators.js';

// Assuming Ace is loaded globally via CDN for now.
// For type information, we'd typically install `ace-builds` or `@types/ace`.
// Let's use a more specific type for Ace if possible, even with global.
declare var ace: {
  edit: (el: HTMLElement | string) => Ace.Editor;
  [key: string]: any; // For other Ace properties like `Range`
};

// Attempt to define Ace types locally if not importing from a module.
// This is a simplified version. In a real project, you'd use npm packages.
declare namespace Ace {
  interface Editor {
    setSession(session: EditSession): void;
    getSession(): EditSession;
    setTheme(theme: string): void;
    setReadOnly(readOnly: boolean): void;
    focus(): void;
    destroy(): void;
    container: HTMLElement;
    [key: string]: any; // For other editor methods/properties
  }

  interface EditSession {
    // Add methods/properties of EditSession that you use
    getValue(): string;
    setValue(val: string, cursorPos?: number): void;
    setMode(mode: string): void;
    getMode(): any;
    setUndoManager(undoManager: UndoManager): void;
    getUndoManager(): UndoManager;
    on(event: string, callback: (...args: any[]) => void): void;
    off(event: string, callback: (...args: any[]) => void): void;
    [key: string]: any;
  }

  interface UndoManager {
    markClean(): void;
    isClean(): boolean;
    reset(): void;
    [key: string]: any;
  }
}


@customElement('ace-editor')
export class AceEditor extends LitElement {
  static styles = css`
    :host {
      display: block;
      width: 100%;
      height: 100%;
    }
    .editor-container {
      width: 100%;
      height: 100%;
      border: 1px solid #ccc; /* Optional border */
    }
    .placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: #aaa;
      background-color: #f8f8f8;
    }
  `;

  @property({ attribute: false }) // attribute: false because EditSession is an object
  session?: Ace.EditSession;

  @property({ type: String })
  theme: string = 'ace/theme/chrome'; // Default theme, can be configured from parent

  @property({ type: Boolean })
  readonly: boolean = false;

  @query('.editor-container')
  private _editorContainer!: HTMLDivElement;

  private _aceEditor?: Ace.Editor;
  private _isEditorInitialized: boolean = false;
  private _currentSession?: Ace.EditSession; // To track the currently set session

  firstUpdated(changedProperties: PropertyValues) {
    super.firstUpdated(changedProperties);
    if (typeof ace === 'undefined' || typeof ace.edit !== 'function') {
      console.error('Ace Editor library not found or ace.edit is not a function. Make sure Ace is loaded correctly.');
      if (this._editorContainer) {
          this._editorContainer.innerHTML = '<div class="placeholder">Error: Ace Editor library not loaded.</div>';
      }
      return;
    }
    this._initializeEditor();
  }

  private _initializeEditor() {
    if (!this._editorContainer || this._isEditorInitialized) return;

    this._aceEditor = ace.edit(this._editorContainer);
    this._aceEditor.setTheme(this.theme);
    this._aceEditor.setReadOnly(this.readonly);
    // this._aceEditor.setOption("useWorker", false); // Disable worker for simpler setup if issues arise

    // If a session is already provided, set it.
    if (this.session) {
      this._aceEditor.setSession(this.session);
      this._currentSession = this.session;
    } else {
      // Create a dummy session or leave it blank if no initial session
      // const dummySession = ace.createEditSession("", "ace/mode/text");
      // this._aceEditor.setSession(dummySession);
    }
    
    // No longer dispatching 'editor-change' from here. Parent will listen to session.
    // If still needed for some reason, it would require listening to this._aceEditor.on('change')
    // but that's tricky when the session changes.

    this._isEditorInitialized = true;
  }

  updated(changedProperties: PropertyValues) {
    super.updated(changedProperties);

    if (!this._aceEditor) return;

    if (changedProperties.has('session')) {
      if (this.session && this.session !== this._currentSession) {
        this._aceEditor.setSession(this.session);
        this._currentSession = this.session;
        // Consider focusing the editor when a new session is set,
        // but only if the editor component itself is focused or intended to be active.
        // this._aceEditor.focus(); 
      } else if (!this.session) {
        // If session is removed, clear the editor or set a default blank session
        // This depends on desired behavior. For now, let's assume a session is always provided when visible.
        // Alternatively, create and set a blank session:
        // const blankSession = ace.createEditSession("", "ace/mode/text");
        // this._aceEditor.setSession(blankSession);
        // this._currentSession = blankSession;
      }
    }

    if (changedProperties.has('theme')) {
      this._aceEditor.setTheme(this.theme);
    }
    if (changedProperties.has('readonly')) {
      this._aceEditor.setReadOnly(this.readonly);
    }
  }

  public focusEditor() {
    if (this._aceEditor) {
      this._aceEditor.focus();
    }
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    if (this._aceEditor) {
      this._aceEditor.destroy();
      // Ace might also add its own container, ensure it's cleaned up if necessary,
      // though usually destroying the editor instance handles its direct DOM elements.
      if (this._aceEditor.container) {
          // this._aceEditor.container.remove(); // This might be too aggressive or handled by destroy. Test.
      }
    }
  }

  render() {
    // The editor-container div is where Ace initializes.
    // If no session, it will be blank or show Ace's default.
    // A custom placeholder could be added here if this.session is undefined.
    if (!this.session && this._isEditorInitialized) { // Show placeholder if no session after init
        return html`<div class="editor-container placeholder">Select a file to view or edit.</div>`;
    }
    return html`<div class="editor-container"></div>`;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'ace-editor': AceEditor;
  }
}
