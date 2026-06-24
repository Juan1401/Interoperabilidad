import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EnvioIhce } from './envio-ihce';

describe('EnvioIhce', () => {
  let component: EnvioIhce;
  let fixture: ComponentFixture<EnvioIhce>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EnvioIhce]
    })
    .compileComponents();

    fixture = TestBed.createComponent(EnvioIhce);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
