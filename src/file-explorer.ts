import { LitElement, html, css } from 'lit';
import { customElement, property } from 'lit/decorators.js';

export interface FileExplorerItem {
  name: string;
  path: string;
  type: 'file' | 'directory';
}

@customElement('file-explorer')
export class FileExplorer extends LitElement {
  static styles = css`
    :host {
      display: block;
      font-family: sans-serif;
      border: 1px solid #eee;
      padding: 8px;
    }
    ul {
      list-style-type: none;
      padding-left: 10px;
      margin: 0;
    }
    li {
      padding: 4px 0;
      cursor: pointer;
      display: flex;
      align-items: center;
    }
    li:hover {
      background-color: #f0f0f0;
    }
    .icon {
      margin-right: 8px;
      width: 20px; /* Fixed width for icons */
      text-align: center;
    }
    .current-path {
      margin-bottom: 8px;
      font-weight: bold;
      word-break: break-all; /* Wrap long paths */
    }
    .go-up {
      margin-bottom: 8px;
      padding: 5px 10px;
      background-color: #e0e0e0;
      border: 1px solid #ccc;
      border-radius: 4px;
      cursor: pointer;
    }
    .go-up:hover {
      background-color: #d0d0d0;
    }
    .disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  `;

  @property({ type: Array })
  items: FileExplorerItem[] = [];

  @property({ type: String })
  currentPath: string = '.';

  @property({ type: String })
  parentPath: string = '';

  private _handleItemClick(item: FileExplorerItem) {
    if (item.type === 'directory') {
      this.dispatchEvent(new CustomEvent('folder-selected', { detail: item, bubbles: true, composed: true }));
    } else {
      this.dispatchEvent(new CustomEvent('file-selected', { detail: item, bubbles: true, composed: true }));
    }
  }

  private _handleGoUpClick() {
    if (this.parentPath) {
      this.dispatchEvent(new CustomEvent('navigate-up', { detail: { path: this.parentPath }, bubbles: true, composed: true }));
    }
  }

  render() {
    return html`
      <div class="current-path">Current: ${this.currentPath}</div>
      ${this.parentPath ? html`
        <button class="go-up" @click=${this._handleGoUpClick}>
          <span class="icon">⬆️</span> Go Up to ${this.parentPath === '.' ? '(root)' : this.parentPath}
        </button>
      ` : html`
        <button class="go-up disabled" disabled>
            <span class="icon">⬆️</span> Go Up
        </button>
      `}
      <ul>
        ${this.items.map(item => html`
          <li @click=${() => this._handleItemClick(item)}>
            <span class="icon">${item.type === 'directory' ? '📁' : '📄'}</span>
            ${item.name}
          </li>
        `)}
      </ul>
    `;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'file-explorer': FileExplorer;
  }
}
