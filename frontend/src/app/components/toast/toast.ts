import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, Toast } from '../../services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './toast.html',
})
export class ToastComponent {
  readonly toast = inject(ToastService);
  readonly toasts = this.toast.toasts;

  readonly typeClass: Record<string, string> = {
    success: 'bg-mint/10 border-mint/20 text-mint',
    error: 'bg-red-50 border-red-200 text-red-700',
    info: 'bg-[#E4EAFF] border-[#D3DAE8] text-[#1E3AD1]',
    warning: 'bg-[#FFF4DB] border-[#F3E8AB] text-[#8A5A00]',
  };

  remove(id: number): void {
    this.toast.remove(id);
  }
}
