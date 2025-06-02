import { LitElement, html, css } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';

export type NotificationType = 'info' | 'success' | 'error' | 'warning';

@customElement('notification-toast')
export class NotificationToast extends LitElement {
  static styles = css`
    :host {
      display: block;
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 1000;
      transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
      opacity: 0;
      pointer-events: none; /* Initially not interactive */
    }
    :host([visible]) {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
      pointer-events: auto; /* Interactive when visible */
    }
    .toast {
      padding: 12px 20px;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      font-family: sans-serif;
      font-size: 1rem;
      color: #fff;
      max-width: 400px;
      text-align: center;
    }
    .toast.info {
      background-color: #2196f3; /* Blue */
    }
    .toast.success {
      background-color: #4caf50; /* Green */
    }
    .toast.error {
      background-color: #f44336; /* Red */
    }
    .toast.warning {
      background-color: #ff9800; /* Orange */
    }
  `;

  @property({ type: String })
  message: string = '';

  @property({ type: String })
  type: NotificationType = 'info';

  @property({ type: Number })
  duration: number = 3000; // Default duration in milliseconds

  @state()
  private _visible: boolean = false;

  private _hideTimeout?: number;

  /**
   * Shows the notification toast with a message, type, and optional duration.
   * @param message The message to display.
   * @param type The type of notification ('info', 'success', 'error', 'warning').
   * @param duration Optional duration in milliseconds. Defaults to the component's duration property.
   */
  public show(message: string, type: NotificationType = 'info', duration?: number) {
    this.message = message;
    this.type = type;
    const showDuration = duration !== undefined ? duration : this.duration;

    // Clear any existing timeout to prevent premature hiding
    if (this._hideTimeout) {
      clearTimeout(this._hideTimeout);
    }

    this._visible = true;
    this.setAttribute('visible', ''); // For CSS attribute selector

    if (showDuration > 0) { // Only set timeout if duration is positive
        this._hideTimeout = window.setTimeout(() => {
        this.hide();
      }, showDuration);
    }
  }

  /**
   * Hides the notification toast.
   */
  public hide() {
    this._visible = false;
    this.removeAttribute('visible');
    if (this._hideTimeout) {
      clearTimeout(this._hideTimeout);
      this._hideTimeout = undefined;
    }
    this.dispatchEvent(new CustomEvent('toast-hidden', { bubbles: true, composed: true }));
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    if (this._hideTimeout) {
      clearTimeout(this._hideTimeout);
    }
  }

  render() {
    if (!this._visible) {
      return html``; // Render nothing if not visible
    }

    return html`
      <div class="toast ${this.type}" role="alert" aria-live="assertive">
        ${this.message}
      </div>
    `;
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'notification-toast': NotificationToast;
  }
}
