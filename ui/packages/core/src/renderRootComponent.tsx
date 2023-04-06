import { Fragment, FunctionComponent, JSX, render } from "preact";
import { GAMECON_KONSTANTY } from "@gamecon/api";


export const renderRootComponent = (
  rootId: string,
  Component: FunctionComponent,
  DevWrap?: FunctionComponent<{ children: JSX.Element }>
) => {
  const root = document.getElementById(rootId);
  if (!root) return;

  root.innerHTML = "";
  const Wrapper =
    GAMECON_KONSTANTY.IS_DEV_SERVER && DevWrap ? DevWrap : Fragment;
  render(
    <Wrapper>
      <Component />
    </Wrapper>,
    root
  );
};
