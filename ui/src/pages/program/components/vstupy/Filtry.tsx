import { FunctionComponent } from "preact";
import { useState } from "preact/hooks";
import {
  useUrlState,
  useUrlStateMožnostiLinie,
} from "../../../../store/program/selektory";
import { nastavFiltrLinie } from "../../../../store/program/slices/urlSlice";

type TFiltryProps = {};

export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const {} = props;

  const [otevřeno, setOtevřeno] = useState(false);

  const řazení = useUrlStateMožnostiLinie();
  const urlState = useUrlState();

  return (
    <>
      <div class="program_filtry_container">
        <button
          class="program_filtry_tlacitko"
          onClick={() => {
            setOtevřeno(!otevřeno);
          }}
        >
          Filtry
        </button>
        <div
          class={
            "program_filtry_dropdown" +
            (otevřeno ? " program_filtry_dropdown_otevreno" : "")
          }
        >
          <ul>
            {řazení.map((linie) => {
              const vybrané = urlState.filtrLinie
                ? urlState.filtrLinie.some((x) => x === linie)
                : true;

              return (
                <li>
                  <label class="program_filtry_moznost">
                    <input
                      type="checkbox"
                      checked={vybrané}
                      onChange={(e) => {
                        nastavFiltrLinie(
                          linie,
                          (e.target as HTMLInputElement)?.checked
                        );
                      }}
                    ></input>
                    {linie}
                  </label>
                </li>
              );
            })}
          </ul>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
