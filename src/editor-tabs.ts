import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

export interface TabInfo {
  id: string;
  name: string;
  path: string;
  unsavedChanges?: boolean;
}

@customElement('editor-tabs')
export class EditorTabs extends LitElement {
  static styles = css`
    :host {
      display: block;
      background-color: #f3f3f3;
      border-bottom: 1px solid #ccc;
      font-family: sans-serif;
    }
    .tabs-container {
      display: flex;
      overflow-x: auto; /* For when many tabs are open */
    }
    .tab {
      display: flex;
      align-items: center;
      padding: 8px 12px;
      cursor: pointer;
      border-right: 1px solid #ccc;
      white-space: nowrap; /* Prevent tab name from wrapping */
      user-select: none; /* Prevent text selection on click */
    }
    .tab:hover {
      background-color: #e0e0e0;
    }
    .tab.active {
      background-color: #fff; /* Or a different highlight color */
      border-bottom: 1px solid #fff; /* To make it look connected to content below */
      position: relative;
      top: 1px; /* To align with the border-bottom */
    }
    .tab-name {
      margin-right: 8px;
    }
    .unsaved-indicator {
      font-weight: bold;
      color: #e74c3c; /* Reddish color for unsaved */
      margin-left: 4px;
    }
    .close-button {
      background: none;
      border: none;
      cursor: pointer;
      padding: 2px 4px;
      margin-left: 8px;
      font-size: 14px;
      line-height: 1;
      border-radius: 3px;
    }
    .close-button:hover {
      background-color: #d0d0d0;
    }
  `;

  @property({ type: Array })
  tabs: TabInfo[] = [];

  @property({ type: String })
  activeTabId: string | null = null;

  private _handleTabClick(tabId: string) {
    this.dispatchEvent(new CustomEvent('tab-selected', { detail: { tabId }, bubbles: true, composed: true }));
  }

  private _handleCloseClick(event: MouseEvent, tabId: string) {
    event.stopPropagation(); // Prevent tab selection when closing
    this.dispatchEvent(new CustomEvent('tab-closed', { detail: { tabId }, bubbles: true, composed: true }));
  }

  render() {
    return html`
      <div class="tabs-container">
        ${this.tabs.map(tab => html`
          <div
            class="tab ${tab.id === this.activeTabId ? 'active' : ''}"
            @click=${() => this._handleTabClick(tab.id)}
            title=${tab.path}
          >
            <span class="tab-name">${tab.name}</span>
            ${tab.unsavedChanges ? html`<span class="unsaved-indicator">*</span>` : ''}
            <button
              class="close-button"
              @click=${(e: MouseEvent) => this._handleCloseClick(e, tab.id)}
              aria-label="Close tab ${tab.name}"
            >
              &times; <!-- HTML entity for 'x' -->
            </button>
          </div>
        `)}
      </div>
    `;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'editor-tabs': EditorTabs;
  }
}
