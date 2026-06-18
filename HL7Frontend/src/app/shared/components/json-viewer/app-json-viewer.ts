import {
  Component,
  Input,
  OnChanges,
  SimpleChanges,
  ChangeDetectionStrategy,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { AccordionModule } from 'primeng/accordion';
import { TagModule } from 'primeng/tag';
import { BadgeModule } from 'primeng/badge';

/**
 * AppJsonViewerComponent
 * ─────────────────────────────────────────────────────────────────────────────
 * Componente standalone recursivo que grafica dinámicamente cualquier JSON.
 *
 * Uso básico:
 *   <app-json-viewer [data]="miObjeto" />
 *
 * Características:
 *  - Recursividad: si el valor de una llave es un objeto/array se renderiza
 *    de nuevo este componente como hijo (p-accordion).
 *  - Colores semánticos en los valores primitivos.
 *  - Compatible con Angular 21 (@if, @for) y PrimeNG Accordion.
 * ─────────────────────────────────────────────────────────────────────────────
 */
@Component({
  selector: 'app-json-viewer',
  standalone: true,
  imports: [
    CommonModule,
    AccordionModule,
    TagModule,
    BadgeModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <!-- Caso: null o undefined -->
    @if (data === null || data === undefined) {
      <span class="jv-null font-italic text-400">null</span>
    }
    <!-- Caso: Primitivo (string, number, boolean) -->
    @else if (!isComplexType(data)) {
      <span [class]="getPrimitiveClass(data)">{{ formatPrimitive(data) }}</span>
    }
    <!-- Caso: Array vacío -->
    @else if (isArray(data) && data.length === 0) {
      <span class="text-400 font-italic">[ ]  (array vacío)</span>
    }
    <!-- Caso: Objeto vacío -->
    @else if (isObject(data) && getKeys(data).length === 0) {
      <span class="text-400 font-italic">&#123; &#125;  (objeto vacío)</span>
    }
    <!-- Caso: Array de primitivos planos (números, strings) -->
    @else if (isArray(data) && !hasComplexItems(data)) {
      <div class="jv-primitive-array flex flex-wrap gap-1 py-1">
        @for (item of data; track $index) {
          <span class="jv-array-pill" [class]="'jv-array-pill ' + getPrimitiveClass(item)">
            {{ formatPrimitive(item) }}
          </span>
        }
      </div>
    }
    <!-- Caso: Objeto o Array complejo → Accordion recursivo -->
    @else {
      <p-accordion [multiple]="true" styleClass="jv-accordion" [(value)]="activePanels">
        @for (key of getKeys(data); track key) {
          <p-accordion-panel [value]="key">
            <p-accordion-header>
              <span class="jv-key text-primary font-bold mr-2">{{ key }}</span>
              @if (isArray(getValue(data, key))) {
                <p-badge
                  [value]="getValue(data, key).length"
                  severity="secondary"
                  styleClass="jv-badge">
                </p-badge>
              }
              @if (isObject(getValue(data, key)) && !isArray(getValue(data, key))) {
                <span class="text-400 text-xs ml-1">&#123; objeto &#125;</span>
              }
              @if (!isComplexType(getValue(data, key)) && getValue(data, key) !== null) {
                <span class="jv-inline-value ml-2" [class]="getPrimitiveClass(getValue(data, key))">
                  → {{ formatPrimitive(getValue(data, key)) }}
                </span>
              }
              @if (getValue(data, key) === null) {
                <span class="jv-null ml-2 font-italic text-400">→ null</span>
              }
            </p-accordion-header>
            <p-accordion-content>
              <div class="jv-content pl-3 border-left-2 border-primary-100">
                <app-json-viewer [data]="getValue(data, key)" [depth]="depth + 1" [expandAll]="expandAll" />
              </div>
            </p-accordion-content>
          </p-accordion-panel>
        }
      </p-accordion>
    }
  `,
  styles: [`
    /* ── Visor JSON – Estilos encapsulados ─────────────────────────────────── */
    :host { display: block; font-family: 'Consolas', 'Courier New', monospace; font-size: 0.83rem; }

    /* Valores primitivos con color semántico */
    .jv-string  { color: #16a34a; }   /* verde */
    .jv-number  { color: #2563eb; }   /* azul */
    .jv-boolean { color: #ea580c; }   /* naranja */
    .jv-null    { color: #94a3b8; }   /* gris */

    /* Llave de cada campo */
    .jv-key { font-size: 0.82rem; letter-spacing: 0.01em; }

    /* Valor inline (primitivos directamente en el header del acordeón) */
    .jv-inline-value { font-size: 0.78rem; }

    /* Array de primitivos: pills */
    .jv-array-pill {
      display: inline-flex;
      align-items: center;
      padding: 0.1rem 0.5rem;
      border-radius: 10px;
      font-size: 0.75rem;
      background: #f1f5f9;
    }

    /* Contenedor de sub-niveles */
    .jv-content { margin-top: 0.25rem; }

    /* Badge de conteo de items en arrays */
    :host ::ng-deep .jv-badge .p-badge { font-size: 0.65rem; min-width: 1.2rem; height: 1.2rem; line-height: 1.2rem; }

    /* Ajuste del accordion para que no tenga tanto padding */
    :host ::ng-deep .jv-accordion .p-accordionheader { padding: 0.4rem 0.75rem; }
    :host ::ng-deep .jv-accordion .p-accordioncontent-content { padding: 0.4rem 0.5rem; }
    :host ::ng-deep .jv-accordion .p-accordionpanel { border-bottom: 1px solid var(--surface-border, #e2e8f0); }
  `]
})
export class AppJsonViewerComponent implements OnChanges {

  /** JSON a visualizar. Puede ser cualquier tipo: objeto, array, primitivo o null. */
  @Input() data: any = null;

  /** Nivel de profundidad actual (para uso interno de la recursividad). */
  @Input() depth: number = 0;

  /** Expande todos los nodos recursivamente si es true. */
  @Input() expandAll: boolean = false;

  /** Llaves del objeto/array procesadas y ordenadas para el @for. */
  keys: string[] = [];

  /** Paneles actualmente abiertos en el acordeón (por llave). */
  activePanels: string[] = [];

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['data']) {
      this.keys = this.getKeys(this.data);
    }
    
    // Si cambia la data o la bandera de expandAll, actualizamos los paneles abiertos
    if (changes['expandAll'] || changes['data']) {
      if (this.expandAll) {
        this.activePanels = [...this.keys];
      } else {
        this.activePanels = [];
      }
    }
  }

  // ─── Helpers de tipo ──────────────────────────────────────────────────────

  /** Devuelve true si el valor es un objeto o array (tipo complejo). */
  isComplexType(val: any): boolean {
    return val !== null && typeof val === 'object';
  }

  /** Devuelve true si el valor es un array. */
  isArray(val: any): boolean {
    return Array.isArray(val);
  }

  /** Devuelve true si el valor es un objeto plano (no array). */
  isObject(val: any): boolean {
    return val !== null && typeof val === 'object' && !Array.isArray(val);
  }

  /** Devuelve true si el array tiene al menos un elemento de tipo complejo. */
  hasComplexItems(val: any[]): boolean {
    return val.some(item => this.isComplexType(item));
  }

  // ─── Helpers de acceso ────────────────────────────────────────────────────

  /**
   * Devuelve las llaves del objeto o los índices del array como strings.
   * Ordena las llaves de objetos alfabéticamente para presentación consistente.
   */
  getKeys(val: any): string[] {
    if (!this.isComplexType(val)) return [];
    if (this.isArray(val)) return (val as any[]).map((_, i) => String(i));
    return Object.keys(val);
  }

  /** Accede al valor por clave en un objeto/array. */
  getValue(obj: any, key: string): any {
    return obj[key];
  }

  // ─── Helpers de formato y estilo ──────────────────────────────────────────

  /** Devuelve la clase CSS según el tipo primitivo del valor. */
  getPrimitiveClass(val: any): string {
    const t = typeof val;
    if (t === 'string')  return 'jv-string';
    if (t === 'number')  return 'jv-number';
    if (t === 'boolean') return 'jv-boolean';
    return 'jv-null';
  }

  /** Formatea un valor primitivo para mostrarlo en pantalla. */
  formatPrimitive(val: any): string {
    if (val === null || val === undefined) return 'null';
    if (typeof val === 'string') return `"${val}"`;
    if (typeof val === 'boolean') return val ? 'true' : 'false';
    return String(val);
  }
}
